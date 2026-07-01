window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.get || !MG.post) return;

  var state = { data: null, contacts: null, loading: false };

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]; }); }
  function text(node) { return String(node && node.textContent || '').replace(/\s+/g, ' ').trim(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function asNumber(value) { return Number(value || 0) || 0; }

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
      var results = await Promise.allSettled([
        MG.get('/api/merchant-canvas/intelligence.php'),
        MG.get('/api/merchant/campaign-contacts.php')
      ]);
      if (results[0].status === 'fulfilled') state.data = payload(results[0].value) || {};
      if (results[1].status === 'fulfilled') state.contacts = payload(results[1].value) || {};
      document.dispatchEvent(new CustomEvent('mg:storeCanvasIntelligenceLoaded', { detail: state.data || {} }));
    } catch (error) {
      toast(error.message || 'Unable to load Store Canvas intelligence.', 'error');
    } finally {
      state.loading = false;
      removeMiddleRow();
      addMerchantAnalyticsTab();
      addMerchantHealthTab();
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
    var fires = zones.reduce(function (total, zone) { return total + asNumber(zone.today && zone.today.fires); }, 0);
    var rewards = zones.reduce(function (total, zone) { return total + asNumber(zone.today && zone.today.rewards_sent); }, 0);
    var messages = zones.reduce(function (total, zone) { return total + asNumber(zone.today && zone.today.messages_sent); }, 0);
    return { activity: activity, journeys: journeys, zones: zones, fires: fires, rewards: rewards, messages: messages };
  }

  function contacts() {
    var data = state.contacts || {};
    return Array.isArray(data.contacts) ? data.contacts : [];
  }

  function contactTags(contact) {
    return Array.isArray(contact.tags) ? contact.tags : (Array.isArray(contact.crm_tags) ? contact.crm_tags : []);
  }

  function hasTag(contact, tag) {
    tag = String(tag || '').toLowerCase();
    return contactTags(contact).some(function (item) { return String(item || '').toLowerCase() === tag; });
  }

  function storeHealth() {
    var analytics = merchantAnalytics();
    var list = contacts();
    var highIntent = list.filter(function (contact) { return asNumber(contact.crm_score) >= 75 || hasTag(contact, 'High Intent'); });
    var followup = list.filter(function (contact) {
      var status = String(contact.result_status || '');
      return hasTag(contact, 'Needs Follow-Up') || ['reward_sent', 'invite_pending', 'email_delivered'].indexOf(status) !== -1;
    });
    var doNotMessage = list.filter(function (contact) { return hasTag(contact, 'Do Not Message'); });
    var rewardsUnclaimed = list.filter(function (contact) {
      var issued = asNumber(contact.issued_count) + asNumber(contact.wallet_count);
      var converted = asNumber(contact.claimed_count) + asNumber(contact.redeemed_count);
      return issued > 0 && converted <= 0;
    });
    var claimsNotRedeemed = list.filter(function (contact) { return asNumber(contact.claimed_count) > asNumber(contact.redeemed_count); });
    var noisyZones = analytics.zones.filter(function (zone) {
      var today = zone.today || {};
      var fires = asNumber(today.fires);
      var actions = asNumber(today.messages_sent) + asNumber(today.rewards_sent);
      return fires >= 5 && actions <= Math.max(1, Math.floor(fires * 0.25));
    });
    var weakZones = analytics.zones.filter(function (zone) {
      var today = zone.today || {};
      return asNumber(today.fires) > 0 && asNumber(today.messages_sent) <= 0 && asNumber(today.rewards_sent) <= 0;
    });
    return {
      analytics: analytics,
      contacts: list,
      highIntent: highIntent,
      followup: followup,
      doNotMessage: doNotMessage,
      rewardsUnclaimed: rewardsUnclaimed,
      claimsNotRedeemed: claimsNotRedeemed,
      noisyZones: noisyZones,
      weakZones: weakZones
    };
  }

  function suggestedMerchantActions(health) {
    var actions = [];
    if (health.highIntent.length) actions.push({ priority: 'high', title: 'Review high-intent customers', copy: health.highIntent.length + ' customers are ready for a stronger offer or direct follow-up.', cta: 'Open Merchant CRM', href: '/merchant-crm.php?filter=high_intent' });
    if (health.followup.length) actions.push({ priority: 'medium', title: 'Clear follow-up queue', copy: health.followup.length + ' contacts need follow-up from a reward, invite, message, or manual tag.', cta: 'View Follow-ups', href: '/merchant-followups.php' });
    if (health.rewardsUnclaimed.length) actions.push({ priority: 'medium', title: 'Recover unclaimed rewards', copy: health.rewardsUnclaimed.length + ' rewards were sent but not claimed yet.', cta: 'Review Rewards', href: '/merchant-crm.php?filter=reward_sent' });
    if (health.claimsNotRedeemed.length) actions.push({ priority: 'medium', title: 'Nudge claimed rewards', copy: health.claimsNotRedeemed.length + ' contacts claimed a reward but have not redeemed it yet.', cta: 'Open Claims', href: '/merchant-claims.php' });
    if (health.noisyZones.length) actions.push({ priority: 'warning', title: 'Tune noisy trigger zones', copy: health.noisyZones.length + ' zones are firing often without enough message/reward actions.', cta: 'Review Triggers', href: '' });
    if (health.weakZones.length) actions.push({ priority: 'warning', title: 'Attach campaigns to quiet zones', copy: health.weakZones.length + ' zones have fires but no visible campaign response today.', cta: 'Review Zones', href: '' });
    if (health.doNotMessage.length) actions.push({ priority: 'safe', title: 'Respect Do Not Message safeguards', copy: health.doNotMessage.length + ' contacts are protected from direct messaging automation.', cta: 'Audit Tags', href: '/merchant-crm.php?filter=do_not_message' });
    if (!actions.length) actions.push({ priority: 'low', title: 'Store health looks stable', copy: 'No urgent CRM or trigger action is visible. Keep monitoring customer movement.', cta: 'Refresh', href: '' });
    return actions.slice(0, 7);
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

  function addMerchantHealthTab() {
    var drawer = qs('.mg-merchant-control-drawer.is-open');
    if (!drawer) return;
    var tabs = qs('[data-merchant-control-tabs]', drawer);
    if (!tabs) return;
    var existing = tabs.querySelector('[data-merchant-health-tab]');
    if (!existing) {
      existing = document.createElement('button');
      existing.type = 'button';
      existing.setAttribute('data-merchant-health-tab', '');
      existing.textContent = 'Store Health';
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

  function renderMerchantHealth(drawer) {
    var body = qs('[data-merchant-settings-body]', drawer);
    var tabs = qs('[data-merchant-control-tabs]', drawer);
    if (!body || !tabs) return;
    qsa('button', tabs).forEach(function (button) { button.classList.remove('is-active'); });
    var tab = tabs.querySelector('[data-merchant-health-tab]');
    if (tab) tab.classList.add('is-active');
    var health = storeHealth();
    var actions = suggestedMerchantActions(health);
    body.innerHTML = '<section class="mg-merchant-health-panel">' +
      '<header><span>Store Health</span><h3>Merchant Action Center</h3><p>Operational CRM view across live Store Canvas movement, customer follow-ups, reward conversion, and trigger performance.</p><button type="button" data-store-health-refresh>Refresh</button></header>' +
      '<div class="mg-merchant-health-grid">' +
        '<article><span>High Intent</span><strong>' + esc(health.highIntent.length) + '</strong><small>customers ready</small></article>' +
        '<article><span>Needs Follow-Up</span><strong>' + esc(health.followup.length) + '</strong><small>contacts queued</small></article>' +
        '<article><span>Unclaimed Rewards</span><strong>' + esc(health.rewardsUnclaimed.length) + '</strong><small>recovery targets</small></article>' +
        '<article><span>Claims Not Redeemed</span><strong>' + esc(health.claimsNotRedeemed.length) + '</strong><small>in-store nudges</small></article>' +
        '<article><span>Noisy Zones</span><strong>' + esc(health.noisyZones.length) + '</strong><small>needs tuning</small></article>' +
        '<article><span>Do Not Message</span><strong>' + esc(health.doNotMessage.length) + '</strong><small>safeguards</small></article>' +
      '</div>' +
      '<section class="mg-merchant-health-actions"><h4>Suggested Merchant Actions</h4>' + actions.map(function (action) {
        var cta = action.href ? '<a href="' + esc(action.href) + '">' + esc(action.cta) + '</a>' : '<button type="button" data-store-health-refresh>' + esc(action.cta) + '</button>';
        return '<article class="is-' + esc(action.priority) + '"><div><strong>' + esc(action.title) + '</strong><span>' + esc(action.copy) + '</span></div>' + cta + '</article>';
      }).join('') + '</section>' +
      '<section class="mg-merchant-health-watch"><h4>Zone Watch</h4>' + (health.analytics.zones.length ? health.analytics.zones.slice(0, 6).map(function (zone) {
        var today = zone.today || {};
        return '<article><strong>' + esc(zone.name || zone.label || 'Trigger Zone') + '</strong><span>' + esc(asNumber(today.fires)) + ' fires · ' + esc(asNumber(today.messages_sent)) + ' messages · ' + esc(asNumber(today.rewards_sent)) + ' rewards</span></article>';
      }).join('') : '<article><strong>No zone activity yet</strong><span>Trigger zone health appears after customers move through the Store Canvas.</span></article>') + '</section>' +
    '</section>';
  }

  document.addEventListener('click', function (event) {
    var analyticsTab = event.target.closest('[data-merchant-analytics-tab]');
    var healthTab = event.target.closest('[data-merchant-health-tab]');
    var refresh = event.target.closest('[data-store-health-refresh]');
    if (!analyticsTab && !healthTab && !refresh) return;
    event.preventDefault();
    event.stopPropagation();
    var drawer = event.target.closest('.mg-merchant-control-drawer') || qs('.mg-merchant-control-drawer.is-open');
    if (analyticsTab && drawer) renderMerchantAnalytics(drawer);
    if (healthTab && drawer) renderMerchantHealth(drawer);
    if (refresh) loadIntel().then(function () { if (drawer) renderMerchantHealth(drawer); });
  }, true);

  window.Microgifter.storeCanvasIntelligence = {
    refresh: loadIntel,
    getData: function () { return state.data || {}; },
    getContacts: function () { return contacts(); },
    getPath: pathForSession,
    getScore: scoreForSession,
    getStoreHealth: storeHealth
  };

  new MutationObserver(function () { window.requestAnimationFrame(function () { removeMiddleRow(); addSimulatorTab(); addMerchantAnalyticsTab(); addMerchantHealthTab(); }); }).observe(root, { childList: true, subtree: true });
  removeMiddleRow();
  loadIntel();
  addSimulatorTab();
  addMerchantAnalyticsTab();
  addMerchantHealthTab();
  window.setInterval(loadIntel, 15000);
})(window, document);
