window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !window.Microgifter || !window.Microgifter.get) return;
  var MG = window.Microgifter;
  var storageKey = 'mgStoreHealthActions:v1';
  var state = { intelligence: null, contacts: null, loading: false };

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]; }); }
  function clean(value) { return String(value || '').replace(/\s+/g, ' ').trim(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function number(value) { return Number(value || 0) || 0; }
  function nowIso() { return new Date().toISOString(); }

  function readActionStore() {
    try { return JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {}; } catch (error) { return {}; }
  }

  function writeActionStore(store) {
    try { window.localStorage.setItem(storageKey, JSON.stringify(store || {})); } catch (error) {}
  }

  function actionKey(action) {
    var condition = action.condition || action.type || action.title || 'action';
    var count = action.count == null ? '' : String(action.count);
    return String(action.type || 'action') + ':' + String(condition).toLowerCase().replace(/[^a-z0-9]+/g, '_') + ':' + count;
  }

  function actionState(action) {
    var store = readActionStore();
    var key = actionKey(action);
    var record = store[key] || null;
    if (!record) return { key: key, status: 'suggested', label: 'Suggested', record: null };
    var status = String(record.status || 'suggested');
    var labels = { suggested: 'Suggested', started: 'Started', completed: 'Completed', snoozed: 'Snoozed', dismissed: 'Dismissed' };
    return { key: key, status: status, label: labels[status] || 'Suggested', record: record };
  }

  function setActionState(action, status, extra) {
    var store = readActionStore();
    var key = actionKey(action);
    var previous = store[key] || {};
    store[key] = Object.assign({}, previous, extra || {}, {
      key: key,
      type: action.type || previous.type || '',
      title: action.title || previous.title || 'Store Health action',
      copy: action.copy || previous.copy || '',
      priority: action.priority || previous.priority || 'low',
      status: status,
      updatedAt: nowIso()
    });
    if (!previous.createdAt) store[key].createdAt = nowIso();
    if (status === 'started') store[key].startedAt = nowIso();
    if (status === 'completed') store[key].completedAt = nowIso();
    if (status === 'snoozed') store[key].snoozedAt = nowIso();
    if (status === 'dismissed') store[key].dismissedAt = nowIso();
    writeActionStore(store);
    return store[key];
  }

  function recentActionHistory(limit) {
    var store = readActionStore();
    return Object.keys(store).map(function (key) { return store[key]; })
      .filter(function (record) { return record && record.title; })
      .sort(function (a, b) { return String(b.updatedAt || '').localeCompare(String(a.updatedAt || '')); })
      .slice(0, limit || 6);
  }

  function shouldHideCompleted(action) {
    var stateInfo = actionState(action);
    if (stateInfo.status !== 'completed' && stateInfo.status !== 'dismissed') return false;
    var completedCount = number(stateInfo.record && stateInfo.record.count);
    var currentCount = number(action.count);
    return currentCount <= completedCount;
  }

  async function refreshHealth() {
    if (state.loading) return;
    state.loading = true;
    try {
      var results = await Promise.allSettled([
        MG.get('/api/merchant-canvas/intelligence.php'),
        MG.get('/api/merchant/campaign-contacts.php')
      ]);
      if (results[0].status === 'fulfilled') state.intelligence = payload(results[0].value) || {};
      if (results[1].status === 'fulfilled') state.contacts = payload(results[1].value) || {};
    } finally {
      state.loading = false;
      addStoreHealthTab();
    }
  }

  function contacts() { return Array.isArray((state.contacts || {}).contacts) ? state.contacts.contacts : []; }
  function zones() { var data = state.intelligence || {}; return Array.isArray(data.zone_metrics) ? data.zone_metrics : []; }
  function tags(contact) { return Array.isArray(contact.tags) ? contact.tags : (Array.isArray(contact.crm_tags) ? contact.crm_tags : []); }
  function hasTag(contact, tag) { tag = String(tag || '').toLowerCase(); return tags(contact).some(function (item) { return String(item || '').toLowerCase() === tag; }); }

  function health() {
    var list = contacts();
    var zoneList = zones();
    var highIntent = list.filter(function (contact) { return number(contact.crm_score) >= 75 || hasTag(contact, 'High Intent'); });
    var followup = list.filter(function (contact) { return hasTag(contact, 'Needs Follow-Up') || ['reward_sent', 'invite_pending', 'email_delivered'].indexOf(String(contact.result_status || '')) !== -1; });
    var doNotMessage = list.filter(function (contact) { return hasTag(contact, 'Do Not Message'); });
    var rewardsUnclaimed = list.filter(function (contact) { var issued = number(contact.issued_count) + number(contact.wallet_count); var converted = number(contact.claimed_count) + number(contact.redeemed_count); return issued > 0 && converted <= 0; });
    var claimsNotRedeemed = list.filter(function (contact) { return number(contact.claimed_count) > number(contact.redeemed_count); });
    var noisyZones = zoneList.filter(function (zone) { var today = zone.today || {}; var fires = number(today.fires); var actions = number(today.messages_sent) + number(today.rewards_sent); return fires >= 5 && actions <= Math.max(1, Math.floor(fires * 0.25)); });
    var weakZones = zoneList.filter(function (zone) { var today = zone.today || {}; return number(today.fires) > 0 && number(today.messages_sent) <= 0 && number(today.rewards_sent) <= 0; });
    return { contacts: list, zones: zoneList, highIntent: highIntent, followup: followup, doNotMessage: doNotMessage, rewardsUnclaimed: rewardsUnclaimed, claimsNotRedeemed: claimsNotRedeemed, noisyZones: noisyZones, weakZones: weakZones };
  }

  function actionsFor(healthData) {
    var actions = [];
    if (healthData.highIntent.length) actions.push({ type: 'send_reward', condition: 'high_intent', count: healthData.highIntent.length, priority: 'high', title: 'Send reward now', copy: healthData.highIntent.length + ' high-intent customers are ready for a direct reward or offer.' });
    if (healthData.followup.length) actions.push({ type: 'follow_up', condition: 'needs_followup', count: healthData.followup.length, priority: 'medium', title: 'Create follow-up task', copy: healthData.followup.length + ' contacts need follow-up from a reward, invite, message, or manual tag.' });
    if (healthData.rewardsUnclaimed.length) actions.push({ type: 'send_reward', condition: 'unclaimed_rewards', count: healthData.rewardsUnclaimed.length, priority: 'medium', title: 'Recover unclaimed rewards', copy: healthData.rewardsUnclaimed.length + ' rewards were sent but not claimed yet.' });
    if (healthData.claimsNotRedeemed.length) actions.push({ type: 'nudge_claims', condition: 'claimed_not_redeemed', count: healthData.claimsNotRedeemed.length, priority: 'medium', title: 'Nudge claimed rewards', copy: healthData.claimsNotRedeemed.length + ' contacts claimed a reward but have not redeemed it yet.' });
    if (healthData.noisyZones.length) actions.push({ type: 'review_triggers', condition: 'noisy_zones', count: healthData.noisyZones.length, priority: 'warning', title: 'Tune noisy trigger zones', copy: healthData.noisyZones.length + ' zones are firing often without enough message/reward actions.' });
    if (healthData.weakZones.length) actions.push({ type: 'review_triggers', condition: 'weak_zones', count: healthData.weakZones.length, priority: 'warning', title: 'Attach campaigns to quiet zones', copy: healthData.weakZones.length + ' zones have fires but no visible campaign response today.' });
    if (healthData.doNotMessage.length) actions.push({ type: 'audit_safeguards', condition: 'do_not_message', count: healthData.doNotMessage.length, priority: 'safe', title: 'Audit Do Not Message safeguards', copy: healthData.doNotMessage.length + ' contacts are protected from direct messaging automation.' });
    actions = actions.filter(function (action) { return !shouldHideCompleted(action); });
    if (!actions.length) actions.push({ type: 'refresh', condition: 'stable', count: 0, priority: 'low', title: 'Store health looks stable', copy: 'No urgent CRM or trigger action is visible. Completed actions will stay hidden until the condition changes.' });
    return actions.slice(0, 7);
  }

  function addStoreHealthTab() {
    var drawer = qs('.mg-merchant-control-drawer.is-open');
    if (!drawer) return;
    var tabs = qs('[data-merchant-control-tabs]', drawer);
    if (!tabs || tabs.querySelector('[data-merchant-store-health-tab]')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('data-merchant-store-health-tab', '');
    button.textContent = 'Store Health';
    tabs.appendChild(button);
  }

  function renderAction(action) {
    var info = actionState(action);
    var note = info.record && info.record.updatedAt ? info.label + ' · ' + new Date(info.record.updatedAt).toLocaleString() : info.label;
    return '<article class="is-' + esc(action.priority) + ' is-status-' + esc(info.status) + '" data-health-action-key="' + esc(info.key) + '">' +
      '<div><strong>' + esc(action.title) + '</strong><span>' + esc(action.copy) + '</span><small>' + esc(note) + '</small></div>' +
      '<div class="mg-health-action-controls"><button type="button" data-merchant-action-execute="' + esc(action.type) + '" data-health-action-key="' + esc(info.key) + '">Execute</button><button type="button" data-health-action-complete="' + esc(info.key) + '">Complete</button><button type="button" data-health-action-snooze="' + esc(info.key) + '">Snooze</button><button type="button" data-health-action-dismiss="' + esc(info.key) + '">Dismiss</button></div>' +
    '</article>';
  }

  function renderHistory() {
    var rows = recentActionHistory(6);
    return '<section class="mg-merchant-health-history"><h4>Action Completion History</h4>' + (rows.length ? rows.map(function (record) {
      return '<article class="is-status-' + esc(record.status || 'suggested') + '"><strong>' + esc(record.title) + '</strong><span>' + esc(record.status || 'suggested') + ' · ' + esc(record.updatedAt ? new Date(record.updatedAt).toLocaleString() : '') + '</span></article>';
    }).join('') : '<article><strong>No completed actions yet</strong><span>Executed, completed, snoozed, and dismissed actions will appear here.</span></article>') + '</section>';
  }

  function renderStoreHealth(drawer) {
    var body = qs('[data-merchant-settings-body]', drawer);
    var tabs = qs('[data-merchant-control-tabs]', drawer);
    if (!body || !tabs) return;
    qsa('button', tabs).forEach(function (button) { button.classList.remove('is-active'); });
    var tab = tabs.querySelector('[data-merchant-store-health-tab]');
    if (tab) tab.classList.add('is-active');
    var data = health();
    var actions = actionsFor(data);
    body.innerHTML = '<section class="mg-merchant-health-panel">' +
      '<header><span>Store Health</span><h3>Merchant Action Center</h3><p>Track suggested, started, completed, snoozed, and dismissed CRM actions from Store Canvas health.</p><button type="button" data-merchant-action-execute="refresh">Refresh</button></header>' +
      '<div class="mg-merchant-health-grid"><article><span>High Intent</span><strong>' + esc(data.highIntent.length) + '</strong><small>ready customers</small></article><article><span>Needs Follow-Up</span><strong>' + esc(data.followup.length) + '</strong><small>contacts queued</small></article><article><span>Unclaimed Rewards</span><strong>' + esc(data.rewardsUnclaimed.length) + '</strong><small>recover now</small></article><article><span>Claims Not Redeemed</span><strong>' + esc(data.claimsNotRedeemed.length) + '</strong><small>needs nudge</small></article><article><span>Noisy Zones</span><strong>' + esc(data.noisyZones.length) + '</strong><small>tune rules</small></article><article><span>Do Not Message</span><strong>' + esc(data.doNotMessage.length) + '</strong><small>safeguards</small></article></div>' +
      '<section class="mg-merchant-health-actions"><h4>Tracked Actions</h4>' + actions.map(renderAction).join('') + '</section>' +
      renderHistory() +
      '<section class="mg-merchant-health-watch"><h4>Zone Watch</h4>' + (data.zones.length ? data.zones.slice(0, 6).map(function (zone) { var today = zone.today || {}; return '<article><strong>' + esc(zone.name || zone.label || 'Trigger Zone') + '</strong><span>' + esc(number(today.fires)) + ' fires · ' + esc(number(today.messages_sent)) + ' messages · ' + esc(number(today.rewards_sent)) + ' rewards</span></article>'; }).join('') : '<article><strong>No zone activity yet</strong><span>Trigger zone health appears after customers move through the Store Canvas.</span></article>') + '</section>' +
    '</section>';
  }

  function first(groupName) { var data = health(); return Array.isArray(data[groupName]) ? data[groupName][0] : null; }
  function profileUrl(contact) { if (!contact) return '/merchant-crm.php'; if (contact.customer_profile_url) return contact.customer_profile_url; if (contact.id) return '/merchant-customer.php?campaign_contact_id=' + encodeURIComponent(contact.id); if (contact.email) return '/merchant-customer.php?email=' + encodeURIComponent(contact.email); return '/merchant-crm.php'; }
  function openVisibleCustomer(contact) { var name = clean(contact && (contact.name || contact.email || contact.id)); if (!name) return false; var cards = qsa('.mg-canvas-avatar-card[data-session-id]', root); var match = cards.find(function (card) { return clean((card.querySelector('strong') || {}).textContent || '').toLowerCase() === name.toLowerCase(); }); if (!match) return false; match.click(); return true; }
  function openTrigger() { var zoneNodes = qsa('[data-canvas-persistent-zone], .mg-canvas-trigger-zone, [data-canvas-trigger-zone]', root); if (zoneNodes.length) { zoneNodes[0].click(); return true; } return false; }

  function actionFromKey(key) { return actionsFor(health()).find(function (action) { return actionKey(action) === key; }) || null; }
  function rerenderOpenDrawer() { var drawer = qs('.mg-merchant-control-drawer.is-open'); if (drawer) renderStoreHealth(drawer); }

  async function execute(type, actionKeyValue) {
    var action = actionFromKey(actionKeyValue) || { type: type, title: type || 'Store Health action', count: 0 };
    if (type !== 'refresh') setActionState(action, 'started', { count: action.count || 0 });
    var contact;
    if (type === 'refresh') { await refreshHealth(); var drawer = qs('.mg-merchant-control-drawer.is-open'); if (drawer) renderStoreHealth(drawer); toast('Store Health refreshed.', 'success'); return; }
    if (type === 'send_reward') { contact = first('highIntent') || first('rewardsUnclaimed') || first('followup'); if (openVisibleCustomer(contact)) { toast('Started action: opened Customer CRM for reward execution.', 'success'); return; } window.location.href = profileUrl(contact) + '#rewards'; return; }
    if (type === 'follow_up') { contact = first('followup') || first('highIntent'); window.location.href = '/merchant-followups.php' + (contact && contact.id ? '?campaign_contact_id=' + encodeURIComponent(contact.id) : ''); return; }
    if (type === 'nudge_claims') { window.location.href = '/merchant-claims.php?filter=claimed_not_redeemed'; return; }
    if (type === 'audit_safeguards') { window.location.href = '/merchant-crm.php?filter=do_not_message'; return; }
    if (type === 'review_triggers') { if (openTrigger()) { toast('Started action: opened trigger zone settings.', 'success'); return; } toast('No trigger zone is visible yet.', 'warning'); return; }
    window.location.href = '/merchant-crm.php';
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-merchant-store-health-tab]');
    if (tab) { event.preventDefault(); event.stopPropagation(); var drawer = tab.closest('.mg-merchant-control-drawer'); refreshHealth().then(function () { if (drawer) renderStoreHealth(drawer); }); return; }
    var complete = event.target.closest('[data-health-action-complete]');
    var snooze = event.target.closest('[data-health-action-snooze]');
    var dismiss = event.target.closest('[data-health-action-dismiss]');
    if (complete || snooze || dismiss) {
      event.preventDefault(); event.stopPropagation();
      var key = (complete || snooze || dismiss).getAttribute(complete ? 'data-health-action-complete' : (snooze ? 'data-health-action-snooze' : 'data-health-action-dismiss')) || '';
      var action = actionFromKey(key) || { type: key, title: 'Store Health action', count: 0 };
      setActionState(action, complete ? 'completed' : (snooze ? 'snoozed' : 'dismissed'), { count: action.count || 0 });
      toast(complete ? 'Action marked completed.' : (snooze ? 'Action snoozed.' : 'Action dismissed.'), 'success');
      rerenderOpenDrawer();
      return;
    }
    var actionButton = event.target.closest('[data-merchant-action-execute]');
    if (!actionButton) return;
    event.preventDefault(); event.stopPropagation();
    var type = actionButton.getAttribute('data-merchant-action-execute') || '';
    var keyValue = actionButton.getAttribute('data-health-action-key') || '';
    actionButton.classList.add('is-executing'); actionButton.textContent = 'Opening...';
    execute(type, keyValue).finally(function () { actionButton.classList.remove('is-executing'); });
  }, true);

  new MutationObserver(function () { window.requestAnimationFrame(addStoreHealthTab); }).observe(root, { childList: true, subtree: true });
  document.addEventListener('mg:storeCanvasIntelligenceLoaded', function (event) { state.intelligence = event.detail || state.intelligence; addStoreHealthTab(); });
  refreshHealth();
})(window, document);
