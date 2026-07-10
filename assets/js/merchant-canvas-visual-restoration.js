window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.get !== 'function' || typeof MG.post !== 'function') return;

  var map = root.querySelector('[data-canvas-map]');
  var customerLayer = root.querySelector('[data-canvas-customers]');
  var zoneLayer = root.querySelector('[data-canvas-server-zones]');
  var merchantNode = root.querySelector('[data-canvas-control-center]');
  if (!map || !customerLayer || !zoneLayer || !merchantNode) return;

  var state = {
    data: null,
    loading: false,
    controlDrawer: null,
    controlTab: 'overview',
    zoneDrawer: null,
    zoneTab: 'settings',
    activeZoneId: '',
    backdrop: null,
    movementTimer: null,
    movementPhase: 0,
    customerObserver: null,
    resizeObserver: null,
    drag: null,
    suppressZoneClick: false,
    returnFocus: null
  };

  function payload(response) { return response && response.data ? response.data : response; }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function formatNumber(value) { return Number(value || 0).toLocaleString(); }
  function clamp(value, minimum, maximum) { return Math.max(minimum, Math.min(maximum, value)); }
  function hashInt(value) {
    var text = String(value || 'canvas');
    var hash = 2166136261;
    for (var index = 0; index < text.length; index += 1) {
      hash ^= text.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
  }
  function selectorEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(String(value));
    return String(value).replace(/["\\]/g, '\\$&');
  }
  function campaignById(id) {
    var campaigns = state.data && Array.isArray(state.data.campaigns) ? state.data.campaigns : [];
    return campaigns.find(function (campaign) { return String(campaign.id) === String(id || ''); }) || null;
  }
  function zoneById(id) {
    var zones = state.data && Array.isArray(state.data.zones) ? state.data.zones : [];
    return zones.find(function (zone) { return String(zone.id) === String(id || ''); }) || null;
  }
  function badge(text, className) {
    return '<span class="mg-canvas-control-badge' + (className ? ' ' + className : '') + '">' + escapeHtml(text) + '</span>';
  }
  function readinessLabel(value) { return value ? badge('Ready') : badge('Needs setup', 'is-warning'); }
  function summaryCard(label, value, detail) {
    return '<article><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong>' + (detail ? '<small>' + escapeHtml(detail) + '</small>' : '') + '</article>';
  }

  function ensureBackdrop() {
    if (state.backdrop && state.backdrop.isConnected) return state.backdrop;
    var backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mg-canvas-control-backdrop';
    backdrop.hidden = true;
    backdrop.setAttribute('aria-label', 'Close Store Canvas panel');
    backdrop.addEventListener('click', closeDrawers);
    document.body.appendChild(backdrop);
    state.backdrop = backdrop;
    return backdrop;
  }
  function setBackdrop(open) {
    ensureBackdrop().hidden = !open;
    document.body.classList.toggle('mg-canvas-control-open', open);
  }
  function closeDrawers() {
    [state.controlDrawer, state.zoneDrawer].forEach(function (drawer) {
      if (!drawer) return;
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
    });
    state.activeZoneId = '';
    zoneLayer.querySelectorAll('.mg-canvas-server-zone').forEach(function (zone) { zone.classList.remove('is-open'); });
    setBackdrop(false);
    if (state.returnFocus && state.returnFocus.isConnected) state.returnFocus.focus();
    state.returnFocus = null;
  }
  function openOnly(drawer, returnFocus) {
    [state.controlDrawer, state.zoneDrawer].forEach(function (item) {
      if (!item || item === drawer) return;
      item.classList.remove('is-open');
      item.setAttribute('aria-hidden', 'true');
    });
    state.returnFocus = returnFocus instanceof HTMLElement ? returnFocus : document.activeElement;
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    setBackdrop(true);
    window.requestAnimationFrame(function () {
      var close = drawer.querySelector('[data-canvas-panel-close]');
      if (close) close.focus();
    });
  }

  function renderOverview() {
    var data = state.data || {};
    var summary = data.summary || {};
    var readiness = data.readiness || {};
    var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
    return '<div class="mg-canvas-control-summary">' +
      summaryCard('Inside Now', formatNumber(summary.active_customers)) +
      summaryCard('Campaigns', formatNumber(summary.recommendable_campaigns), 'recommendable') +
      summaryCard('Trigger Zones', formatNumber(summary.active_trigger_zones), 'active') +
      summaryCard('Sent Today', formatNumber(summary.recommendations_today), 'recommendations') +
      summaryCard('Execution', 'Guarded', 'manual authority') +
      summaryCard('Reward Path', 'Wallet', 'then Inbox + PPPM') +
      '</div>' +
      '<section class="mg-canvas-control-card"><h3>Live Store Canvas</h3><p>Visual movement and campaign zones are active. Customer-impacting actions remain server validated. Browser position and overlap never send messages, notifications, or rewards.</p><div class="mg-canvas-control-actions" style="margin-top:12px"><button class="mg-canvas-control-action is-primary" type="button" data-control-refresh>Refresh live state</button><a class="mg-canvas-control-action" href="/merchant-campaigns.php">Manage campaigns</a></div></section>' +
      '<section class="mg-canvas-control-card"><h3>System readiness</h3><div class="mg-canvas-control-list">' +
        '<article class="mg-canvas-control-row"><div><strong>Canvas sessions</strong><span>Live merchant/customer presence authority</span></div>' + readinessLabel(readiness.canvas_schema) + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Manual operation safeguards</strong><span>CRM, Do Not Message, idempotency receipts</span></div>' + readinessLabel(readiness.manual_operations_schema) + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Trigger zone inventory</strong><span>Merchant-owned server records</span></div>' + readinessLabel(readiness.trigger_zone_schema) + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Customer journey intelligence</strong><span>Detailed timeline and analytics</span></div>' + readinessLabel(readiness.journey_analytics_schema) + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Automatic trigger engine</strong><span>Planned server-authoritative execution phase</span></div>' + badge('Contained', 'is-warning') + '</article>' +
      '</div></section>' +
      '<section class="mg-canvas-control-card"><h3>Optimization suggestions</h3><div class="mg-canvas-control-list">' + suggestions.map(function (item) {
        return '<article class="mg-canvas-control-row mg-canvas-control-suggestion" data-level="' + escapeHtml(item.level || 'info') + '"><div><strong>' + escapeHtml(item.title || 'Suggestion') + '</strong><span>' + escapeHtml(item.detail || '') + '</span></div>' + badge(item.level || 'info', item.level === 'high' || item.level === 'medium' ? 'is-warning' : '') + '</article>';
      }).join('') + '</div></section>';
  }

  function renderCampaigns() {
    var campaigns = state.data && Array.isArray(state.data.campaigns) ? state.data.campaigns : [];
    if (!campaigns.length) return '<section class="mg-canvas-control-card"><h3>No campaigns available</h3><p>Create an active public campaign with an attached reward template.</p><div class="mg-canvas-control-actions" style="margin-top:12px"><a class="mg-canvas-control-action is-primary" href="/merchant-campaigns.php">Create campaign</a></div></section>';
    return '<section class="mg-canvas-control-card"><h3>Campaign recommendation inventory</h3><p>Ready campaigns can be sent as customer notifications. No reward is issued until campaign completion.</p><div class="mg-canvas-control-list" style="margin-top:12px">' + campaigns.map(function (campaign) {
      var status = campaign.can_recommend ? badge('Ready') : badge(campaign.status || 'Unavailable', 'is-warning');
      var inventory = campaign.remaining == null ? 'Unlimited campaign inventory' : formatNumber(campaign.remaining) + ' rewards remaining';
      return '<article class="mg-canvas-control-row"><div><strong>' + escapeHtml(campaign.title) + '</strong><span>' + escapeHtml(campaign.type_label + ' · ' + (campaign.reward_template_title || 'No active reward')) + '</span><small>' + escapeHtml(inventory) + '</small></div><div>' + status + (campaign.public_url ? '<a class="mg-canvas-control-action" href="' + escapeHtml(campaign.public_url) + '" target="_blank" rel="noopener">Preview</a>' : '') + '</div></article>';
    }).join('') + '</div></section>';
  }

  function renderTriggers() {
    var zones = state.data && Array.isArray(state.data.zones) ? state.data.zones : [];
    var readiness = state.data && state.data.readiness ? state.data.readiness : {};
    if (!readiness.trigger_zone_schema) return '<section class="mg-canvas-control-card"><h3>Trigger zone schema required</h3><p>The visual canvas remains available, but trigger zones require the existing Store Canvas trigger-zone migration.</p></section>';
    return '<section class="mg-canvas-control-card"><div style="display:flex;align-items:center;justify-content:space-between;gap:12px"><div><h3 style="margin-bottom:4px">Server-owned trigger zones</h3><p>Zones are visual and configurable. Automatic execution remains contained.</p></div><button type="button" class="mg-canvas-control-action is-primary" data-control-add-zone>Add zone</button></div><div class="mg-canvas-control-list" style="margin-top:12px">' + (zones.length ? zones.map(function (zone) {
      var campaign = campaignById(zone.campaign_id);
      return '<article class="mg-canvas-control-row"><div><strong>' + escapeHtml(zone.name || 'Trigger Zone') + '</strong><span>' + escapeHtml(campaign ? campaign.title : (zone.campaign_title || 'No campaign assigned')) + '</span><small>Priority ' + escapeHtml(zone.priority || 3) + ' · ' + escapeHtml(String(zone.cooldown_policy || 'fifteen_minutes').replace(/_/g, ' ')) + '</small></div><button type="button" data-control-open-zone="' + escapeHtml(zone.id) + '">Open</button></article>';
    }).join('') : '<article class="mg-canvas-control-row"><div><strong>No trigger zones yet</strong><span>Create a zone and bind it to a campaign.</span></div></article>') + '</div></section>';
  }

  function renderIntelligence() {
    return '<section class="mg-canvas-control-card"><h3>Predictive commerce intelligence</h3><p>Store Canvas is the live visual layer for campaign recommendation, customer journey measurement, and future-demand signals.</p></section>' +
      '<section class="mg-canvas-control-card"><h3>Recommended intelligence loop</h3><div class="mg-canvas-control-list">' +
      '<article class="mg-canvas-control-row"><div><strong>1. Detect intent</strong><span>Visits, views, messages, campaign history, Wallet activity, claims, and redemptions.</span></div>' + badge('Observe') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>2. Recommend a campaign</strong><span>The merchant chooses from governed campaign inventory.</span></div>' + badge('Merchant approved') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>3. Notify the customer</strong><span>A clickable notification opens the canonical campaign page.</span></div>' + badge('Live') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>4. Complete and reward</strong><span>Campaign completion issues to Wallet, then projects into Inbox and PPPM.</span></div>' + badge('Campaign authority') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>5. Learn from outcomes</strong><span>Sent, opened, participated, issued, claimed, redeemed, returned, and revenue influenced.</span></div>' + badge('Learning loop') + '</article>' +
      '</div></section>';
  }

  function renderSecurity() {
    var security = state.data && state.data.security ? state.data.security : {};
    return '<section class="mg-canvas-control-card"><h3>Security boundaries</h3><div class="mg-canvas-control-list">' +
      '<article class="mg-canvas-control-row"><div><strong>Merchant ownership</strong><span>Campaigns, zones, sessions, and customer actions are scoped on the server.</span></div>' + readinessLabel(security.merchant_scope === 'server_enforced') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Customer authority</strong><span>Recommendations require a real active Store Canvas session.</span></div>' + readinessLabel(security.customer_scope === 'active_store_session') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Do Not Message</strong><span>Recommendation notifications and direct messages honor the CRM block.</span></div>' + readinessLabel(security.do_not_message_enforced) + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Duplicate protection</strong><span>Stable request keys and action receipts prevent repeat notifications.</span></div>' + readinessLabel(security.idempotency_receipts) + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Reward separation</strong><span>Recommendation notifications never issue rewards. Campaign completion owns Wallet issuance.</span></div>' + badge('Enforced') + '</article>' +
      '<article class="mg-canvas-control-row"><div><strong>Browser overlap</strong><span>Visual movement and zones cannot create messages, notifications, or rewards.</span></div>' + badge('Blocked') + '</article>' +
      '</div></section>';
  }

  function ensureControlDrawer() {
    if (state.controlDrawer && state.controlDrawer.isConnected) return state.controlDrawer;
    var drawer = document.createElement('aside');
    drawer.className = 'mg-canvas-control-drawer';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('aria-labelledby', 'mg-canvas-control-title');
    drawer.innerHTML = '<header class="mg-canvas-control-head"><div><span>Merchant Avatar</span><h2 id="mg-canvas-control-title">Merchant Control Center</h2></div><button class="mg-canvas-control-close" type="button" data-canvas-panel-close aria-label="Close Merchant Control Center">&times;</button></header><nav class="mg-canvas-control-tabs" data-control-tabs aria-label="Merchant control sections"></nav><div class="mg-canvas-control-body" data-control-body></div>';
    document.body.appendChild(drawer);
    state.controlDrawer = drawer;
    return drawer;
  }

  function renderControlDrawer() {
    var drawer = ensureControlDrawer();
    var tabs = [['overview','Overview'],['campaigns','Campaigns'],['triggers','Trigger Zones'],['intelligence','Intelligence'],['security','Security']];
    drawer.querySelector('[data-control-tabs]').innerHTML = tabs.map(function (item) {
      return '<button type="button" data-control-tab="' + item[0] + '" class="' + (state.controlTab === item[0] ? 'is-active' : '') + '">' + item[1] + '</button>';
    }).join('');
    var body = drawer.querySelector('[data-control-body]');
    if (!state.data) {
      body.innerHTML = '<section class="mg-canvas-control-card"><h3>Loading Store Canvas controls…</h3><p>Reading merchant-scoped campaign, trigger, readiness, and security state.</p></section>';
      return;
    }
    body.innerHTML = state.controlTab === 'campaigns' ? renderCampaigns() : state.controlTab === 'triggers' ? renderTriggers() : state.controlTab === 'intelligence' ? renderIntelligence() : state.controlTab === 'security' ? renderSecurity() : renderOverview();
  }

  function campaignOptions(selectedId) {
    var campaigns = state.data && Array.isArray(state.data.campaigns) ? state.data.campaigns : [];
    var html = '<option value="">No campaign assigned</option>';
    campaigns.forEach(function (campaign) {
      html += '<option value="' + escapeHtml(campaign.id) + '"' + (String(campaign.id) === String(selectedId || '') ? ' selected' : '') + '>' + escapeHtml(campaign.title + ' · ' + campaign.type_label) + '</option>';
    });
    return html;
  }

  function zoneTabContent(zone) {
    var campaign = campaignById(zone.campaign_id);
    if (state.zoneTab === 'campaign') {
      return '<section class="mg-canvas-control-card"><h3>Assigned campaign</h3>' + (campaign ? '<div class="mg-canvas-control-row"><div><strong>' + escapeHtml(campaign.title) + '</strong><span>' + escapeHtml(campaign.type_label + ' · ' + (campaign.reward_template_title || 'No reward')) + '</span><small>Recommendation: notification only · Reward: after campaign completion</small></div>' + (campaign.can_recommend ? badge('Ready') : badge('Not ready', 'is-warning')) + '</div>' : '<p>No campaign is assigned. Select an active public campaign in Settings.</p>') + '</section>';
    }
    if (state.zoneTab === 'eligibility') {
      return '<section class="mg-canvas-control-card"><h3>Server eligibility gates</h3><div class="mg-canvas-control-list">' +
        '<article class="mg-canvas-control-row"><div><strong>Active customer session</strong><span>Customer must be inside this merchant Store Canvas.</span></div>' + badge('Required') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Merchant campaign ownership</strong><span>Assigned campaign must belong to the signed-in merchant.</span></div>' + badge('Required') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Public active campaign</strong><span>Campaign must be active, public, within date limits, and have reward inventory.</span></div>' + badge('Required') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Communication permission</strong><span>Do Not Message prevents recommendation delivery.</span></div>' + badge('Required') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Frequency protection</strong><span>' + escapeHtml(String(zone.cooldown_policy || 'fifteen_minutes').replace(/_/g, ' ')) + '</span></div>' + badge('Configured') + '</article>' +
      '</div></section>';
    }
    if (state.zoneTab === 'delivery') {
      return '<section class="mg-canvas-control-card"><h3>Campaign delivery contract</h3><div class="mg-canvas-control-list">' +
        '<article class="mg-canvas-control-row"><div><strong>Merchant recommendation</strong><span>The merchant selects a campaign and sends a clickable notification.</span></div>' + badge('Manual') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Customer participation</strong><span>The notification opens the campaign page for requirement completion.</span></div>' + badge('Customer action') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Reward issue</strong><span>The campaign engine issues the approved reward to wallet.php only after completion.</span></div>' + badge('Separate authority') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Lifecycle projection</strong><span>Wallet issuance is forwarded into Inbox and the PPPM lifecycle.</span></div>' + badge('Canonical') + '</article>' +
      '</div></section>';
    }
    if (state.zoneTab === 'server') {
      return '<section class="mg-canvas-control-card"><h3>Server-authoritative readiness</h3><p>This zone is a merchant-owned database record. Its placement and campaign binding are durable. Visual customer overlap is non-authoritative until the trigger event engine is deployed.</p><div class="mg-canvas-control-list" style="margin-top:12px">' +
        '<article class="mg-canvas-control-row"><div><strong>Visual placement</strong><span>Stored as bounded percentages on the merchant zone record.</span></div>' + badge('Live') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Automatic execution</strong><span>Proximity chat and overlap-triggered actions remain disabled.</span></div>' + badge('Contained', 'is-warning') + '</article>' +
        '<article class="mg-canvas-control-row"><div><strong>Next engine contract</strong><span>Event → eligibility → approved campaign → notification → audit.</span></div>' + badge('Server-ready') + '</article>' +
      '</div></section>';
    }
    return '<form class="mg-canvas-control-form" data-zone-form>' +
      '<section class="mg-canvas-control-card"><h3>Zone settings</h3><div class="mg-canvas-control-grid">' +
        '<label>Zone name<input name="name" maxlength="160" value="' + escapeHtml(zone.name || 'Campaign Zone') + '"></label>' +
        '<label>Status<select name="status"><option value="active"' + (zone.status === 'active' ? ' selected' : '') + '>Active</option><option value="paused"' + (zone.status === 'paused' ? ' selected' : '') + '>Paused</option></select></label>' +
        '<label>Assigned campaign<select name="campaign_id">' + campaignOptions(zone.campaign_id) + '</select></label>' +
        '<label>Priority<select name="priority">' + [1,2,3,4,5].map(function (value) { return '<option value="' + value + '"' + (Number(zone.priority || 3) === value ? ' selected' : '') + '>' + value + '</option>'; }).join('') + '</select></label>' +
        '<label>Frequency limit<select name="cooldown_policy">' + [['five_minutes','Five minutes'],['fifteen_minutes','Fifteen minutes'],['one_hour','One hour'],['once_per_visit','Once per visit'],['once_per_customer_day','Once per customer per day']].map(function (item) { return '<option value="' + item[0] + '"' + (String(zone.cooldown_policy || 'fifteen_minutes') === item[0] ? ' selected' : '') + '>' + item[1] + '</option>'; }).join('') + '</select></label>' +
        '<label>Execution mode<select disabled><option>Merchant-approved recommendation</option></select><small>Automatic execution remains contained.</small></label>' +
      '</div></section>' +
      '<section class="mg-canvas-control-card"><h3>Recommendation copy</h3><label>Suggested notification note<textarea name="auto_message_text" maxlength="1000" rows="5" placeholder="Optional merchant message shown when recommending this campaign…">' + escapeHtml(zone.auto_message_text || '') + '</textarea><small>This is a reusable draft. Sending still requires an explicit merchant action from the customer drawer.</small></label></section>' +
    '</form>';
  }

  function ensureZoneDrawer() {
    if (state.zoneDrawer && state.zoneDrawer.isConnected) return state.zoneDrawer;
    var drawer = document.createElement('aside');
    drawer.className = 'mg-canvas-zone-drawer';
    drawer.setAttribute('role', 'dialog');
    drawer.setAttribute('aria-modal', 'true');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.setAttribute('aria-labelledby', 'mg-canvas-zone-title');
    drawer.innerHTML = '<header class="mg-canvas-control-head"><div><span>Trigger Zone</span><h2 id="mg-canvas-zone-title" data-zone-title>Campaign Zone</h2></div><button class="mg-canvas-control-close" type="button" data-canvas-panel-close aria-label="Close trigger zone">&times;</button></header><nav class="mg-canvas-control-tabs" data-zone-tabs aria-label="Trigger zone sections"></nav><div class="mg-canvas-control-body" data-zone-body></div><footer class="mg-canvas-control-footer"><button type="button" class="mg-canvas-control-action" data-zone-delete>Archive zone</button><p class="mg-canvas-control-status" data-zone-status></p><button type="button" class="mg-canvas-control-action is-primary" data-zone-save>Save zone</button></footer>';
    document.body.appendChild(drawer);
    state.zoneDrawer = drawer;
    return drawer;
  }
  function setZoneStatus(message, className) {
    var status = ensureZoneDrawer().querySelector('[data-zone-status]');
    status.textContent = message || '';
    status.className = 'mg-canvas-control-status' + (className ? ' ' + className : '');
  }
  function renderZoneDrawer() {
    var zone = zoneById(state.activeZoneId);
    if (!zone) return;
    var drawer = ensureZoneDrawer();
    drawer.querySelector('[data-zone-title]').textContent = zone.name || 'Campaign Zone';
    var tabs = [['settings','Settings'],['campaign','Campaign'],['eligibility','Eligibility'],['delivery','Delivery'],['server','Server']];
    drawer.querySelector('[data-zone-tabs]').innerHTML = tabs.map(function (item) { return '<button type="button" data-zone-tab="' + item[0] + '" class="' + (state.zoneTab === item[0] ? 'is-active' : '') + '">' + item[1] + '</button>'; }).join('');
    drawer.querySelector('[data-zone-body]').innerHTML = zoneTabContent(zone);
    drawer.querySelector('[data-zone-save]').hidden = state.zoneTab !== 'settings';
  }

  function openControlDrawer(trigger) {
    renderControlDrawer();
    openOnly(ensureControlDrawer(), trigger || merchantNode);
    if (!state.data) loadControlData(true);
  }
  function openZoneDrawer(id, trigger) {
    if (!zoneById(id)) return;
    state.activeZoneId = String(id);
    state.zoneTab = 'settings';
    zoneLayer.querySelectorAll('.mg-canvas-server-zone').forEach(function (node) { node.classList.toggle('is-open', node.dataset.zoneId === state.activeZoneId); });
    renderZoneDrawer();
    openOnly(ensureZoneDrawer(), trigger || zoneLayer.querySelector('[data-zone-id="' + selectorEscape(state.activeZoneId) + '"]'));
  }
  function cooldownSeconds(policy) { return policy === 'five_minutes' ? 300 : policy === 'one_hour' ? 3600 : policy === 'once_per_customer_day' ? 86400 : policy === 'once_per_visit' ? 3600 : 900; }
  function readZoneForm(zone) {
    var form = state.zoneDrawer ? state.zoneDrawer.querySelector('[data-zone-form]') : null;
    if (!form || !zone) return zone;
    zone.name = String(form.elements.name.value || 'Campaign Zone').trim().slice(0, 160) || 'Campaign Zone';
    zone.status = form.elements.status.value || 'active';
    zone.campaign_id = form.elements.campaign_id.value || '';
    zone.priority = clamp(parseInt(form.elements.priority.value || 3, 10) || 3, 1, 5);
    zone.cooldown_policy = form.elements.cooldown_policy.value || 'fifteen_minutes';
    zone.cooldown_seconds = cooldownSeconds(zone.cooldown_policy);
    zone.auto_message_text = String(form.elements.auto_message_text.value || '').trim().slice(0, 1000);
    zone.automation_action = 'notify_only';
    zone.fallback_action = 'analytics_only';
    zone.notify_merchant = 1;
    return zone;
  }
  function zoneRequest(zone, create) {
    return {
      id: create ? '' : zone.id,
      name: zone.name || 'Campaign Zone',
      trigger_key: zone.trigger_key || 'store_canvas_campaign_zone',
      campaign_id: zone.campaign_id || '',
      priority: zone.priority || 3,
      x: Number(zone.x || 8), y: Number(zone.y || 20), width: Number(zone.width || 20), height: Number(zone.height || 15),
      status: zone.status || 'active',
      automation_action: 'notify_only',
      cooldown_policy: zone.cooldown_policy || 'fifteen_minutes',
      cooldown_seconds: zone.cooldown_seconds || 900,
      auto_message_text: zone.auto_message_text || '',
      fallback_action: 'analytics_only',
      crm_segment_name: zone.crm_segment_name || '',
      notify_merchant: 1
    };
  }
  async function saveZone(zone, create) {
    if (!zone) return null;
    setZoneStatus('Saving…', 'is-saving');
    try {
      var response = payload(await MG.post('/api/merchant-canvas/trigger-zone-save.php', zoneRequest(zone, create))) || {};
      if (Array.isArray(response.zones) && state.data) state.data.zones = response.zones;
      else if (response.zone && state.data) {
        var found = false;
        state.data.zones = (state.data.zones || []).map(function (item) { if (String(item.id) === String(zone.id)) { found = true; return response.zone; } return item; });
        if (!found) state.data.zones.push(response.zone);
      }
      state.activeZoneId = response.zone ? String(response.zone.id) : state.activeZoneId;
      renderZones(); renderControlDrawer();
      if (state.zoneDrawer && state.zoneDrawer.classList.contains('is-open')) renderZoneDrawer();
      setZoneStatus('Zone saved. Automatic execution remains contained.', 'is-success');
      return response.zone || null;
    } catch (error) {
      setZoneStatus(error.message || 'Unable to save zone.', 'is-error');
      throw error;
    }
  }
  async function addZone() {
    if (!state.data) return;
    var campaigns = (state.data.campaigns || []).filter(function (campaign) { return campaign.can_recommend; });
    var count = (state.data.zones || []).length;
    var zone = { id:'', name:'Campaign Zone ' + (count + 1), trigger_key:'store_canvas_campaign_zone_' + (count + 1), campaign_id:campaigns[0] ? campaigns[0].id : '', priority:3, x:8 + ((count * 19) % 68), y:20 + ((count * 17) % 58), width:20, height:15, status:'active', cooldown_policy:'once_per_visit', cooldown_seconds:3600, auto_message_text:'', automation_action:'notify_only', fallback_action:'analytics_only', notify_merchant:1 };
    try { var saved = await saveZone(zone, true); if (saved) openZoneDrawer(saved.id, document.querySelector('[data-control-add-zone]')); } catch (error) {}
  }
  async function deleteZone() {
    var zone = zoneById(state.activeZoneId);
    if (!zone || !window.confirm('Archive this trigger zone? Historical records are retained.')) return;
    setZoneStatus('Archiving…', 'is-saving');
    try {
      var response = payload(await MG.post('/api/merchant-canvas/trigger-zone-delete.php', { id: zone.id })) || {};
      if (state.data) state.data.zones = Array.isArray(response.zones) ? response.zones : (state.data.zones || []).filter(function (item) { return String(item.id) !== String(zone.id); });
      closeDrawers(); renderZones(); renderControlDrawer();
    } catch (error) { setZoneStatus(error.message || 'Unable to archive zone.', 'is-error'); }
  }

  function renderZones() {
    var zones = state.data && Array.isArray(state.data.zones) ? state.data.zones : [];
    zoneLayer.replaceChildren();
    zones.forEach(function (zone) {
      var campaign = campaignById(zone.campaign_id);
      var node = document.createElement('div');
      node.className = 'mg-canvas-server-zone' + (zone.status === 'paused' ? ' is-paused' : '') + (String(zone.id) === state.activeZoneId ? ' is-open' : '');
      node.dataset.zoneId = String(zone.id); node.tabIndex = 0; node.setAttribute('role', 'button'); node.setAttribute('aria-label', 'Open ' + (zone.name || 'campaign zone'));
      node.style.left = clamp(Number(zone.x || 0), 0, 96) + '%'; node.style.top = clamp(Number(zone.y || 0), 0, 96) + '%'; node.style.width = clamp(Number(zone.width || 20), 8, 100) + '%'; node.style.height = clamp(Number(zone.height || 15), 8, 100) + '%'; node.style.zIndex = String(10 + clamp(Number(zone.priority || 3), 1, 5));
      node.innerHTML = '<span class="mg-canvas-server-zone-copy"><strong>' + escapeHtml(zone.name || 'Campaign Zone') + '</strong><small>' + escapeHtml(campaign ? campaign.title : (zone.campaign_title || 'No campaign assigned')) + '</small></span><span class="mg-canvas-server-zone-state">' + escapeHtml(zone.status === 'paused' ? 'Paused' : 'Server-ready') + '</span><span class="mg-canvas-server-zone-resize" data-zone-resize aria-hidden="true"></span>';
      zoneLayer.appendChild(node);
    });
  }

  function positionCustomers(forceStatic) {
    var cards = Array.from(customerLayer.querySelectorAll('[data-session-id]'));
    if (!cards.length) return;
    var width = map.clientWidth, height = map.clientHeight;
    if (width < 320 || height < 420) return;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var phase = forceStatic || reduced ? 0 : state.movementPhase;
    var centerX = width / 2, centerY = height / 2 + 18;
    var radiusX = Math.max(95, Math.min(width * .37, width / 2 - 120));
    var radiusY = Math.max(120, Math.min(height * .31, height / 2 - 145));
    cards.forEach(function (card, index) {
      var seed = hashInt(card.dataset.sessionId || String(index));
      var angle = ((seed % 360) + index * 73 + phase * (13 + seed % 9)) * Math.PI / 180;
      var ripple = 1 + (((seed >> 4) % 17) - 8) / 100;
      var cardWidth = card.offsetWidth || 190, cardHeight = card.offsetHeight || 68;
      var x = clamp(centerX + Math.cos(angle) * radiusX * ripple - cardWidth / 2, 18, Math.max(18, width - cardWidth - 18));
      var y = clamp(centerY + Math.sin(angle) * radiusY * ripple - cardHeight / 2, 112, Math.max(112, height - cardHeight - 70));
      card.style.left = Math.round(x) + 'px'; card.style.top = Math.round(y) + 'px';
      card.dataset.visualMovement = 'presentation-only';
    });
  }
  function scheduleMovement() {
    if (state.movementTimer) window.clearInterval(state.movementTimer);
    positionCustomers(true);
    state.movementTimer = window.setInterval(function () { state.movementPhase += 1; positionCustomers(false); }, 6500);
  }

  async function loadControlData(force) {
    if (state.loading && !force) return;
    state.loading = true;
    try {
      state.data = payload(await MG.get('/api/merchant-canvas/control-center.php')) || {};
      renderZones(); renderControlDrawer();
      document.dispatchEvent(new CustomEvent('mg:merchantCanvasControlData', { detail: state.data }));
    } catch (error) {
      if (state.controlDrawer) state.controlDrawer.querySelector('[data-control-body]').innerHTML = '<section class="mg-canvas-control-card"><h3>Control Center unavailable</h3><p>' + escapeHtml(error.message || 'Unable to load merchant controls.') + '</p></section>';
    } finally { state.loading = false; }
  }

  function pointerStart(event) {
    var node = event.target.closest('.mg-canvas-server-zone');
    if (!node || !zoneLayer.contains(node)) return;
    var zone = zoneById(node.dataset.zoneId); if (!zone) return;
    var rect = map.getBoundingClientRect();
    state.drag = { zone:zone, node:node, pointerId:event.pointerId, startX:event.clientX, startY:event.clientY, x:Number(zone.x || 0), y:Number(zone.y || 0), width:Number(zone.width || 20), height:Number(zone.height || 15), mapWidth:rect.width, mapHeight:rect.height, resize:Boolean(event.target.closest('[data-zone-resize]')), moved:false };
    node.classList.add('is-dragging'); if (node.setPointerCapture) node.setPointerCapture(event.pointerId); event.preventDefault();
  }
  function pointerMove(event) {
    var drag = state.drag; if (!drag || event.pointerId !== drag.pointerId) return;
    var dx = ((event.clientX - drag.startX) / drag.mapWidth) * 100, dy = ((event.clientY - drag.startY) / drag.mapHeight) * 100;
    if (Math.abs(dx) + Math.abs(dy) > .35) drag.moved = true;
    if (drag.resize) { drag.zone.width = clamp(drag.width + dx, 8, 100 - Number(drag.zone.x || 0)); drag.zone.height = clamp(drag.height + dy, 8, 100 - Number(drag.zone.y || 0)); }
    else { drag.zone.x = clamp(drag.x + dx, 0, 100 - Number(drag.zone.width || 20)); drag.zone.y = clamp(drag.y + dy, 0, 100 - Number(drag.zone.height || 15)); }
    drag.node.style.left = Number(drag.zone.x || 0) + '%'; drag.node.style.top = Number(drag.zone.y || 0) + '%'; drag.node.style.width = Number(drag.zone.width || 20) + '%'; drag.node.style.height = Number(drag.zone.height || 15) + '%';
  }
  function pointerEnd(event) {
    var drag = state.drag; if (!drag || event.pointerId !== drag.pointerId) return;
    drag.node.classList.remove('is-dragging'); state.drag = null;
    if (drag.moved) { state.suppressZoneClick = true; window.setTimeout(function () { state.suppressZoneClick = false; }, 120); saveZone(drag.zone, false).catch(function () {}); }
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-canvas-panel-close]')) return closeDrawers();
    var controlButton = event.target.closest('[data-canvas-open-control]'); if (controlButton) return openControlDrawer(controlButton);
    var merchant = event.target.closest('[data-canvas-control-center]'); if (merchant && root.contains(merchant)) return openControlDrawer(merchant);
    var controlTab = event.target.closest('[data-control-tab]'); if (controlTab && state.controlDrawer && state.controlDrawer.contains(controlTab)) { state.controlTab = controlTab.dataset.controlTab || 'overview'; renderControlDrawer(); return; }
    if (event.target.closest('[data-control-refresh]')) { loadControlData(true); var refresh = root.querySelector('[data-canvas-refresh]'); if (refresh) refresh.click(); return; }
    if (event.target.closest('[data-control-add-zone]')) return void addZone();
    var openZone = event.target.closest('[data-control-open-zone]'); if (openZone) return openZoneDrawer(openZone.dataset.controlOpenZone, openZone);
    var zoneTab = event.target.closest('[data-zone-tab]'); if (zoneTab && state.zoneDrawer && state.zoneDrawer.contains(zoneTab)) { var current = zoneById(state.activeZoneId); if (state.zoneTab === 'settings' && current) readZoneForm(current); state.zoneTab = zoneTab.dataset.zoneTab || 'settings'; renderZoneDrawer(); return; }
    if (event.target.closest('[data-zone-save]')) { var active = zoneById(state.activeZoneId); if (active) saveZone(readZoneForm(active), false).catch(function () {}); return; }
    if (event.target.closest('[data-zone-delete]')) return void deleteZone();
    var zoneNode = event.target.closest('.mg-canvas-server-zone'); if (zoneNode && zoneLayer.contains(zoneNode) && !state.suppressZoneClick && !event.target.closest('[data-zone-resize]')) openZoneDrawer(zoneNode.dataset.zoneId, zoneNode);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && ((state.controlDrawer && state.controlDrawer.classList.contains('is-open')) || (state.zoneDrawer && state.zoneDrawer.classList.contains('is-open')))) { event.preventDefault(); closeDrawers(); return; }
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-canvas-control-center]')) { event.preventDefault(); openControlDrawer(event.target); return; }
    var zone = event.target.closest('.mg-canvas-server-zone'); if (zone && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openZoneDrawer(zone.dataset.zoneId, zone); }
  });
  zoneLayer.addEventListener('pointerdown', pointerStart); document.addEventListener('pointermove', pointerMove); document.addEventListener('pointerup', pointerEnd); document.addEventListener('pointercancel', pointerEnd);

  state.customerObserver = new MutationObserver(function () { window.requestAnimationFrame(function () { positionCustomers(false); }); });
  state.customerObserver.observe(customerLayer, { childList: true });
  if (typeof ResizeObserver === 'function') { state.resizeObserver = new ResizeObserver(function () { positionCustomers(true); renderZones(); }); state.resizeObserver.observe(map); }

  ensureBackdrop(); scheduleMovement(); loadControlData(false);
  window.addEventListener('beforeunload', function () { if (state.movementTimer) window.clearInterval(state.movementTimer); if (state.customerObserver) state.customerObserver.disconnect(); if (state.resizeObserver) state.resizeObserver.disconnect(); });
})(window, document);
