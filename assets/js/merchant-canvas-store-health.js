window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !window.Microgifter || !window.Microgifter.get) return;
  var MG = window.Microgifter;
  var state = { intelligence: null, contacts: null, loading: false };

  function qs(selector, scope) { return (scope || document).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || document).querySelectorAll(selector)); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]; }); }
  function clean(value) { return String(value || '').replace(/\s+/g, ' ').trim(); }
  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function number(value) { return Number(value || 0) || 0; }

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

  function contacts() {
    return Array.isArray((state.contacts || {}).contacts) ? state.contacts.contacts : [];
  }

  function zones() {
    var data = state.intelligence || {};
    return Array.isArray(data.zone_metrics) ? data.zone_metrics : [];
  }

  function tags(contact) {
    return Array.isArray(contact.tags) ? contact.tags : (Array.isArray(contact.crm_tags) ? contact.crm_tags : []);
  }

  function hasTag(contact, tag) {
    tag = String(tag || '').toLowerCase();
    return tags(contact).some(function (item) { return String(item || '').toLowerCase() === tag; });
  }

  function health() {
    var list = contacts();
    var zoneList = zones();
    var highIntent = list.filter(function (contact) { return number(contact.crm_score) >= 75 || hasTag(contact, 'High Intent'); });
    var followup = list.filter(function (contact) {
      return hasTag(contact, 'Needs Follow-Up') || ['reward_sent', 'invite_pending', 'email_delivered'].indexOf(String(contact.result_status || '')) !== -1;
    });
    var doNotMessage = list.filter(function (contact) { return hasTag(contact, 'Do Not Message'); });
    var rewardsUnclaimed = list.filter(function (contact) {
      var issued = number(contact.issued_count) + number(contact.wallet_count);
      var converted = number(contact.claimed_count) + number(contact.redeemed_count);
      return issued > 0 && converted <= 0;
    });
    var claimsNotRedeemed = list.filter(function (contact) { return number(contact.claimed_count) > number(contact.redeemed_count); });
    var noisyZones = zoneList.filter(function (zone) {
      var today = zone.today || {};
      var fires = number(today.fires);
      var actions = number(today.messages_sent) + number(today.rewards_sent);
      return fires >= 5 && actions <= Math.max(1, Math.floor(fires * 0.25));
    });
    var weakZones = zoneList.filter(function (zone) {
      var today = zone.today || {};
      return number(today.fires) > 0 && number(today.messages_sent) <= 0 && number(today.rewards_sent) <= 0;
    });
    return { contacts: list, zones: zoneList, highIntent: highIntent, followup: followup, doNotMessage: doNotMessage, rewardsUnclaimed: rewardsUnclaimed, claimsNotRedeemed: claimsNotRedeemed, noisyZones: noisyZones, weakZones: weakZones };
  }

  function actionsFor(healthData) {
    var actions = [];
    if (healthData.highIntent.length) actions.push({ type: 'send_reward', priority: 'high', title: 'Send reward now', copy: healthData.highIntent.length + ' high-intent customers are ready for a direct reward or offer.' });
    if (healthData.followup.length) actions.push({ type: 'follow_up', priority: 'medium', title: 'Create follow-up task', copy: healthData.followup.length + ' contacts need follow-up from a reward, invite, message, or manual tag.' });
    if (healthData.rewardsUnclaimed.length) actions.push({ type: 'send_reward', priority: 'medium', title: 'Recover unclaimed rewards', copy: healthData.rewardsUnclaimed.length + ' rewards were sent but not claimed yet.' });
    if (healthData.claimsNotRedeemed.length) actions.push({ type: 'nudge_claims', priority: 'medium', title: 'Nudge claimed rewards', copy: healthData.claimsNotRedeemed.length + ' contacts claimed a reward but have not redeemed it yet.' });
    if (healthData.noisyZones.length) actions.push({ type: 'review_triggers', priority: 'warning', title: 'Tune noisy trigger zones', copy: healthData.noisyZones.length + ' zones are firing often without enough message/reward actions.' });
    if (healthData.weakZones.length) actions.push({ type: 'review_triggers', priority: 'warning', title: 'Attach campaigns to quiet zones', copy: healthData.weakZones.length + ' zones have fires but no visible campaign response today.' });
    if (healthData.doNotMessage.length) actions.push({ type: 'audit_safeguards', priority: 'safe', title: 'Audit Do Not Message safeguards', copy: healthData.doNotMessage.length + ' contacts are protected from direct messaging automation.' });
    if (!actions.length) actions.push({ type: 'refresh', priority: 'low', title: 'Store health looks stable', copy: 'No urgent CRM or trigger action is visible. Keep monitoring customer movement.' });
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
      '<header><span>Store Health</span><h3>Merchant Action Center</h3><p>Execute CRM actions from live Store Canvas movement, reward state, follow-ups, tags, and trigger performance.</p><button type="button" data-merchant-action-execute="refresh">Refresh</button></header>' +
      '<div class="mg-merchant-health-grid">' +
        '<article><span>High Intent</span><strong>' + esc(data.highIntent.length) + '</strong><small>ready customers</small></article>' +
        '<article><span>Needs Follow-Up</span><strong>' + esc(data.followup.length) + '</strong><small>contacts queued</small></article>' +
        '<article><span>Unclaimed Rewards</span><strong>' + esc(data.rewardsUnclaimed.length) + '</strong><small>recover now</small></article>' +
        '<article><span>Claims Not Redeemed</span><strong>' + esc(data.claimsNotRedeemed.length) + '</strong><small>needs nudge</small></article>' +
        '<article><span>Noisy Zones</span><strong>' + esc(data.noisyZones.length) + '</strong><small>tune rules</small></article>' +
        '<article><span>Do Not Message</span><strong>' + esc(data.doNotMessage.length) + '</strong><small>safeguards</small></article>' +
      '</div>' +
      '<section class="mg-merchant-health-actions"><h4>Executable Actions</h4>' + actions.map(function (action) {
        return '<article class="is-' + esc(action.priority) + '"><div><strong>' + esc(action.title) + '</strong><span>' + esc(action.copy) + '</span><small>Ready to execute</small></div><button type="button" data-merchant-action-execute="' + esc(action.type) + '">Execute</button></article>';
      }).join('') + '</section>' +
      '<section class="mg-merchant-health-watch"><h4>Zone Watch</h4>' + (data.zones.length ? data.zones.slice(0, 6).map(function (zone) {
        var today = zone.today || {};
        return '<article><strong>' + esc(zone.name || zone.label || 'Trigger Zone') + '</strong><span>' + esc(number(today.fires)) + ' fires · ' + esc(number(today.messages_sent)) + ' messages · ' + esc(number(today.rewards_sent)) + ' rewards</span></article>';
      }).join('') : '<article><strong>No zone activity yet</strong><span>Trigger zone health appears after customers move through the Store Canvas.</span></article>') + '</section>' +
    '</section>';
  }

  function first(groupName) {
    var data = health();
    return Array.isArray(data[groupName]) ? data[groupName][0] : null;
  }

  function profileUrl(contact) {
    if (!contact) return '/merchant-crm.php';
    if (contact.customer_profile_url) return contact.customer_profile_url;
    if (contact.id) return '/merchant-customer.php?campaign_contact_id=' + encodeURIComponent(contact.id);
    if (contact.email) return '/merchant-customer.php?email=' + encodeURIComponent(contact.email);
    return '/merchant-crm.php';
  }

  function openVisibleCustomer(contact) {
    var name = clean(contact && (contact.name || contact.email || contact.id));
    if (!name) return false;
    var cards = qsa('.mg-canvas-avatar-card[data-session-id]', root);
    var match = cards.find(function (card) { return clean((card.querySelector('strong') || {}).textContent || '').toLowerCase() === name.toLowerCase(); });
    if (!match) return false;
    match.click();
    return true;
  }

  function openTrigger() {
    var zones = qsa('[data-canvas-persistent-zone], .mg-canvas-trigger-zone, [data-canvas-trigger-zone]', root);
    if (zones.length) { zones[0].click(); return true; }
    return false;
  }

  async function execute(type) {
    var contact;
    if (type === 'refresh') { await refreshHealth(); var drawer = qs('.mg-merchant-control-drawer.is-open'); if (drawer) renderStoreHealth(drawer); toast('Store Health refreshed.', 'success'); return; }
    if (type === 'send_reward') { contact = first('highIntent') || first('rewardsUnclaimed') || first('followup'); if (openVisibleCustomer(contact)) { toast('Opened Customer CRM for reward execution.', 'success'); return; } window.location.href = profileUrl(contact) + '#rewards'; return; }
    if (type === 'follow_up') { contact = first('followup') || first('highIntent'); window.location.href = '/merchant-followups.php' + (contact && contact.id ? '?campaign_contact_id=' + encodeURIComponent(contact.id) : ''); return; }
    if (type === 'nudge_claims') { window.location.href = '/merchant-claims.php?filter=claimed_not_redeemed'; return; }
    if (type === 'audit_safeguards') { window.location.href = '/merchant-crm.php?filter=do_not_message'; return; }
    if (type === 'review_triggers') { if (openTrigger()) { toast('Opened trigger zone settings.', 'success'); return; } toast('No trigger zone is visible yet.', 'warning'); return; }
    window.location.href = '/merchant-crm.php';
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-merchant-store-health-tab]');
    if (tab) {
      event.preventDefault();
      event.stopPropagation();
      var drawer = tab.closest('.mg-merchant-control-drawer');
      refreshHealth().then(function () { if (drawer) renderStoreHealth(drawer); });
      return;
    }
    var action = event.target.closest('[data-merchant-action-execute]');
    if (!action) return;
    event.preventDefault();
    event.stopPropagation();
    var type = action.getAttribute('data-merchant-action-execute') || '';
    action.classList.add('is-executing');
    action.textContent = 'Opening...';
    execute(type).finally(function () { action.classList.remove('is-executing'); });
  }, true);

  new MutationObserver(function () { window.requestAnimationFrame(addStoreHealthTab); }).observe(root, { childList: true, subtree: true });
  document.addEventListener('mg:storeCanvasIntelligenceLoaded', function (event) { state.intelligence = event.detail || state.intelligence; addStoreHealthTab(); });
  refreshHealth();
})(window, document);
