window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;
  var merchant = root.querySelector('.mg-canvas-merchant-node');
  if (!merchant) return;

  var key = 'mgCanvasMerchantBehavior:v2';
  var legacyKey = 'mgCanvasMerchantBehavior:v1';
  var conversationKey = 'mgCanvasMerchantConversations:v1';
  var drawer = null;
  var activeTab = 'engagement';
  var campaigns = [];
  var campaignsLoaded = false;
  var roamTimer = null;
  var roamStep = 0;

  var fallbackCampaigns = [
    { id: 'join_newsletter', title: 'Join Newsletter', status: 'active' },
    { id: 'reward_redeem', title: 'Reward Redeem', status: 'active' },
    { id: 'refer_earn', title: 'Refer & Earn', status: 'active' }
  ];

  var defaults = {
    interaction_mode: 'guided',
    response_tone: 'warm_professional',
    greeting_message: 'Welcome in. I can help with rewards, offers, or questions while you are browsing.',
    auto_greet: 1,
    recommend_campaigns: 1,
    handoff_behavior: 'offer_handoff',
    trigger_reaction: 'reward_context',
    idle_timeout_seconds: 120,
    max_turns: 6,
    pause_after_seconds: 30,
    allow_end_conversation: 1,
    end_on_idle: 1,
    end_on_max_turns: 1,
    end_after_reward: 1,
    end_when_customer_leaves: 1,
    closing_message: 'Thanks for visiting. I will step back for now, but I can help again whenever you need me.',
    walk_enabled: 0,
    movement_mode: 'assigned_route',
    merchant_placement: 'center',
    route_style: 'front_counter_loop',
    attached_campaigns: ['join_newsletter', 'reward_redeem', 'refer_earn'],
    history_window: '7d'
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function boolInt(value) { return value === true || value === 1 || value === '1' || value === 'true' || value === 'on' ? 1 : 0; }
  function intVal(value, fallback) { var n = parseInt(value, 10); return Number.isFinite(n) ? n : fallback; }
  function titleCase(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); }); }

  function loadLegacy() {
    var stored = {};
    try { stored = JSON.parse(window.localStorage.getItem(legacyKey) || '{}') || {}; } catch (error) { stored = {}; }
    return stored;
  }

  function normalize(settings) {
    settings = Object.assign({}, defaults, settings || {});
    settings.auto_greet = boolInt(settings.auto_greet);
    settings.recommend_campaigns = boolInt(settings.recommend_campaigns);
    settings.allow_end_conversation = boolInt(settings.allow_end_conversation);
    settings.end_on_idle = boolInt(settings.end_on_idle);
    settings.end_on_max_turns = boolInt(settings.end_on_max_turns);
    settings.end_after_reward = boolInt(settings.end_after_reward);
    settings.end_when_customer_leaves = boolInt(settings.end_when_customer_leaves);
    settings.walk_enabled = boolInt(settings.walk_enabled);
    settings.idle_timeout_seconds = Math.max(15, intVal(settings.idle_timeout_seconds, defaults.idle_timeout_seconds));
    settings.max_turns = Math.max(1, intVal(settings.max_turns, defaults.max_turns));
    settings.pause_after_seconds = Math.max(0, intVal(settings.pause_after_seconds, defaults.pause_after_seconds));
    settings.attached_campaigns = Array.isArray(settings.attached_campaigns) ? settings.attached_campaigns.map(String) : defaults.attached_campaigns.slice();
    return settings;
  }

  function load() {
    var stored = {};
    try { stored = JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (error) { stored = {}; }
    if (!Object.keys(stored).length) stored = loadLegacy();
    return normalize(stored);
  }

  function save(settings) {
    settings = normalize(settings || load());
    try { window.localStorage.setItem(key, JSON.stringify(settings)); } catch (error) {}
    merchant.dataset.interactionMode = settings.interaction_mode;
    merchant.dataset.responseTone = settings.response_tone;
    merchant.dataset.autoGreet = String(settings.auto_greet);
    merchant.dataset.walkAround = String(settings.walk_enabled);
    merchant.dataset.merchantPlacement = settings.merchant_placement;
    root.dataset.merchantInteractionMode = settings.interaction_mode;
    root.dataset.merchantAutoGreet = String(settings.auto_greet);
    root.dataset.merchantWalkAround = String(settings.walk_enabled);
    root.dataset.merchantConversationExit = settings.allow_end_conversation ? 'enabled' : 'disabled';
    applyMovement(settings);
    window.dispatchEvent(new CustomEvent('mg:merchantCanvasBehaviorChanged', { detail: settings }));
    return settings;
  }

  function options(items, current) {
    return items.map(function (item) {
      return '<option value="' + esc(item[0]) + '"' + (String(current) === String(item[0]) ? ' selected' : '') + '>' + esc(item[1]) + '</option>';
    }).join('');
  }

  function title(settings) {
    if (settings.interaction_mode === 'observe_only') return 'Observe only';
    if (!boolInt(settings.auto_greet)) return 'Manual greeting';
    if (settings.interaction_mode === 'proactive') return 'Proactive helper';
    if (settings.interaction_mode === 'manual_first') return 'Manual first';
    return 'Guided helper';
  }

  function campaignList() { return campaignsLoaded && campaigns.length ? campaigns : fallbackCampaigns; }
  function activeCampaigns(settings) { return settings.attached_campaigns.filter(function (id) { return campaignList().some(function (campaign) { return String(campaign.id) === String(id); }); }); }

  async function loadCampaigns() {
    if (!MG.get) { campaignsLoaded = true; return; }
    try {
      var response = await MG.get('/api/merchant-canvas/reward-options.php');
      var data = response && response.data ? response.data : response;
      campaigns = Array.isArray(data && data.campaigns) ? data.campaigns.filter(function (campaign) { return campaign && campaign.available !== false; }).map(function (campaign) {
        return { id: String(campaign.id || ''), title: String(campaign.title || 'Campaign'), status: 'active' };
      }).filter(function (campaign) { return campaign.id !== ''; }) : [];
    } catch (error) { campaigns = []; }
    campaignsLoaded = true;
    if (drawer && drawer.classList.contains('is-open')) render();
  }

  function ensureDrawer() {
    if (drawer && drawer.isConnected) return drawer;
    drawer = document.createElement('aside');
    drawer.className = 'mg-canvas-merchant-settings-drawer mg-merchant-control-drawer';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.innerHTML = '<div class="mg-merchant-settings-head mg-merchant-control-head"><div><span>Merchant Avatar</span><h2>Merchant Control Center</h2></div><button type="button" data-merchant-settings-close aria-label="Close merchant avatar settings">×</button></div><nav class="mg-merchant-control-tabs" data-merchant-control-tabs aria-label="Merchant control tabs"></nav><div class="mg-merchant-settings-body mg-merchant-control-body" data-merchant-settings-body></div><div class="mg-merchant-settings-foot mg-merchant-control-foot"><button type="button" class="mg-merchant-reset" data-merchant-reset>Reset to Defaults</button><p class="mg-merchant-settings-status" data-merchant-settings-status role="status"></p><button type="button" class="mg-btn mg-btn-primary" data-merchant-settings-save>Save Changes</button></div>';
    document.body.appendChild(drawer);
    drawer.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-merchant-tab]');
      if (tab) {
        save(readForm());
        activeTab = tab.getAttribute('data-merchant-tab') || 'engagement';
        render();
        return;
      }
      if (event.target.closest('[data-merchant-settings-close]')) { closeDrawer(); return; }
      if (event.target.closest('[data-merchant-reset]')) { save(defaults); status('Defaults restored.', 'success'); render(); return; }
      if (event.target.closest('[data-merchant-settings-save]')) { save(readForm()); status('Saved.', 'success'); render(); return; }
      if (event.target.closest('[data-merchant-place-outside]')) { var next = Object.assign(load(), { merchant_placement: 'outside', walk_enabled: 1 }); save(next); status('Merchant moved outside route.', 'success'); render(); return; }
      if (event.target.closest('[data-merchant-place-center]')) { var center = Object.assign(load(), { merchant_placement: 'center' }); save(center); status('Merchant returned to center.', 'success'); render(); return; }
    });
    drawer.addEventListener('change', function () { save(readForm()); status('Saved.', 'success'); renderSummaryOnly(); });
    drawer.addEventListener('input', function () { save(readForm()); status('Saved.', 'success'); renderSummaryOnly(); preview(); });
    return drawer;
  }

  function status(message, state) {
    var node = drawer ? drawer.querySelector('[data-merchant-settings-status]') : null;
    if (!node) return;
    node.textContent = message || '';
    node.className = 'mg-merchant-settings-status' + (state ? ' is-' + state : '');
  }

  function openDrawer() {
    ensureDrawer();
    render();
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    merchant.classList.add('is-settings-open');
  }

  function closeDrawer() {
    if (!drawer) return;
    save(readForm());
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    merchant.classList.remove('is-settings-open');
  }

  function renderTabs() {
    var tabs = ensureDrawer().querySelector('[data-merchant-control-tabs]');
    if (!tabs) return;
    var items = [
      ['controls', 'Controls'],
      ['engagement', 'Engagement Logic'],
      ['campaigns', 'Campaigns'],
      ['movement', 'Movement'],
      ['history', 'History']
    ];
    tabs.innerHTML = items.map(function (item) {
      return '<button type="button" data-merchant-tab="' + esc(item[0]) + '" class="' + (activeTab === item[0] ? 'is-active' : '') + '">' + esc(item[1]) + '</button>';
    }).join('');
  }

  function summaryCards(settings) {
    return '<section class="mg-merchant-control-summary" data-merchant-control-summary>' +
      '<article><span>Attached Campaigns</span><strong>' + activeCampaigns(settings).length + '</strong><small>active</small><b>▣</b></article>' +
      '<article><span>Movement</span><strong>' + (settings.walk_enabled ? 'Walk Around' : 'Stationary') + '</strong><small>' + (settings.walk_enabled ? 'Enabled' : 'Disabled') + '</small><b>⌁</b></article>' +
      '<article><span>History</span><strong>Available</strong><small>view logs</small><b>◷</b></article>' +
      '</section>';
  }

  function renderSummaryOnly() {
    if (!drawer) return;
    var host = drawer.querySelector('[data-merchant-control-summary]');
    if (!host) return;
    var wrapper = document.createElement('div');
    wrapper.innerHTML = summaryCards(load());
    host.replaceWith(wrapper.firstElementChild);
  }

  function render() {
    var settings = load();
    var body = ensureDrawer().querySelector('[data-merchant-settings-body]');
    renderTabs();
    if (!body) return;
    body.innerHTML = '<form class="mg-merchant-settings-form mg-merchant-control-form" data-merchant-settings-form>' + summaryCards(settings) + renderTab(settings) + '</form>';
  }

  function renderTab(settings) {
    if (activeTab === 'controls') return renderControls(settings);
    if (activeTab === 'campaigns') return renderCampaigns(settings);
    if (activeTab === 'movement') return renderMovement(settings);
    if (activeTab === 'history') return renderHistory(settings);
    return renderEngagement(settings);
  }

  function renderControls(settings) {
    return '<section class="mg-merchant-control-card"><h3>Merchant Control Bar</h3><p>Control how the merchant avatar greets customers, hands off conversations, attaches campaigns, and moves around the Store Canvas.</p><div class="mg-merchant-control-grid">' +
      '<label>Interaction mode<select name="interaction_mode">' + options([['guided','Guided helper'],['proactive','Proactive helper'],['observe_only','Observe only'],['manual_first','Manual first']], settings.interaction_mode) + '</select></label>' +
      '<label>Response tone<select name="response_tone">' + options([['warm_professional','Warm professional'],['concise','Concise'],['hospitality','Hospitality'],['sales_assist','Sales assist'],['premium','Premium']], settings.response_tone) + '</select></label>' +
      '<label>Human handoff<select name="handoff_behavior">' + options([['offer_handoff','Offer handoff when asked'],['always_offer','Always offer handoff'],['never_offer','Never offer handoff automatically']], settings.handoff_behavior) + '</select></label>' +
      '<label>Trigger reaction<select name="trigger_reaction">' + options([['message_when_triggered','Message when triggered'],['reward_context','Reward context'],['analytics_only','Analytics only']], settings.trigger_reaction) + '</select></label>' +
      '</div></section>' + renderCompactCards(settings);
  }

  function renderEngagement(settings) {
    return '<section class="mg-merchant-control-card"><h3>Conversational Behavior</h3><div class="mg-merchant-control-grid">' +
      '<label>Interaction mode<select name="interaction_mode">' + options([['guided','Guided helper'],['proactive','Proactive helper'],['observe_only','Observe only'],['manual_first','Manual first']], settings.interaction_mode) + '</select></label>' +
      '<label>Response tone<select name="response_tone">' + options([['warm_professional','Warm professional'],['concise','Concise'],['hospitality','Hospitality'],['sales_assist','Sales assist'],['premium','Premium']], settings.response_tone) + '</select></label>' +
      '<label class="mg-merchant-toggle-row"><span><b>Auto-greet customers</b><small>Automatically greet when customers enter.</small></span><input type="checkbox" name="auto_greet" value="1"' + (settings.auto_greet ? ' checked' : '') + '></label>' +
      '<label>Disengage after idle time<select name="idle_timeout_seconds">' + options([['30','30 seconds'],['60','60 seconds'],['120','120 seconds'],['300','5 minutes']], settings.idle_timeout_seconds) + '</select></label>' +
      '<label class="mg-merchant-wide">Greeting message<textarea name="greeting_message" rows="4" maxlength="200">' + esc(settings.greeting_message) + '</textarea><small>' + esc(String(settings.greeting_message || '').length) + '/200</small></label>' +
      '<label>Maximum back-and-forth turns<select name="max_turns">' + options([['3','3 turns'],['4','4 turns'],['6','6 turns'],['8','8 turns'],['10','10 turns']], settings.max_turns) + '</select></label>' +
      '<label>Pause after no customer response<select name="pause_after_seconds">' + options([['0','No pause'],['15','15 seconds'],['30','30 seconds'],['60','60 seconds']], settings.pause_after_seconds) + '</select></label>' +
      '<label>Trigger reaction<select name="trigger_reaction">' + options([['message_when_triggered','Message when triggered'],['reward_context','Reward context'],['analytics_only','Analytics only']], settings.trigger_reaction) + '</select></label>' +
      '<label>Handoff behavior<select name="handoff_behavior">' + options([['offer_handoff','Offer handoff when asked'],['always_offer','Always offer handoff'],['never_offer','Never offer handoff automatically']], settings.handoff_behavior) + '</select></label>' +
      '<label class="mg-merchant-toggle-row"><span><b>Allow merchant to end conversation</b><small>End gracefully with a closing message.</small></span><input type="checkbox" name="allow_end_conversation" value="1"' + (settings.allow_end_conversation ? ' checked' : '') + '></label>' +
      '</div></section>' +
      '<section class="mg-merchant-control-card"><h3>Disengagement Rules <small>(Conversation Exit)</small></h3><div class="mg-merchant-rule-grid">' + renderRule('end_on_idle', 'Idle timeout', 'End conversation after no activity.', settings.end_on_idle, 'idle_timeout_seconds', settings.idle_timeout_seconds, [['30','30 seconds'],['60','60 seconds'],['120','120 seconds'],['300','5 minutes']]) + renderRule('end_on_max_turns', 'Max replies', 'End after reaching max back-and-forth.', settings.end_on_max_turns, 'max_turns', settings.max_turns, [['3','3 replies'],['4','4 replies'],['6','6 replies'],['8','8 replies']]) + renderRule('end_after_reward', 'Stop after reward delivered', 'End conversation once reward is delivered.', settings.end_after_reward) + renderRule('end_when_customer_leaves', 'End when customer leaves area', 'End conversation when customer exits store.', settings.end_when_customer_leaves) + '</div><label class="mg-merchant-wide">Closing message<textarea name="closing_message" rows="3" maxlength="240">' + esc(settings.closing_message) + '</textarea></label></section>' + renderCompactCards(settings);
  }

  function renderRule(name, titleText, copy, checked, selectName, selectValue, selectOptions) {
    return '<article class="mg-merchant-rule"><label class="mg-merchant-switch"><span><b>' + esc(titleText) + '</b><small>' + esc(copy) + '</small></span><input type="checkbox" name="' + esc(name) + '" value="1"' + (checked ? ' checked' : '') + '></label>' + (selectName ? '<select name="' + esc(selectName) + '">' + options(selectOptions || [], selectValue) + '</select>' : '') + '</article>';
  }

  function renderCampaigns(settings) {
    return '<section class="mg-merchant-control-card"><h3>Attached Campaigns</h3><p>Campaigns attached here are the merchant avatar\'s recommended offers during chat and trigger-zone interactions.</p><div class="mg-merchant-campaign-list">' + campaignList().map(function (campaign) { var checked = settings.attached_campaigns.indexOf(String(campaign.id)) !== -1; return '<label><input type="checkbox" name="attached_campaigns" value="' + esc(campaign.id) + '"' + (checked ? ' checked' : '') + '><span><strong>' + esc(campaign.title) + '</strong><small>' + esc(campaign.status || 'active') + '</small></span></label>'; }).join('') + '</div></section>' + renderCompactCards(settings);
  }

  function renderMovement(settings) {
    return '<section class="mg-merchant-control-card"><h3>Walk Around Feature</h3><div class="mg-merchant-control-grid">' +
      '<label class="mg-merchant-toggle-row"><span><b>Enable walk around</b><small>Merchant can roam within the assigned route.</small></span><input type="checkbox" name="walk_enabled" value="1"' + (settings.walk_enabled ? ' checked' : '') + '></label>' +
      '<label>Movement mode<select name="movement_mode">' + options([['assigned_route','Assigned route'],['near_active_customer','Near active customer'],['front_counter','Front counter'],['stationary','Stationary']], settings.movement_mode) + '</select></label>' +
      '<label>Route style<select name="route_style">' + options([['front_counter_loop','Front counter loop'],['campaign_route','Campaign route'],['support_loop','Support loop'],['entry_exit_loop','Entry/exit loop']], settings.route_style) + '</select></label>' +
      '<label>Placement<select name="merchant_placement">' + options([['center','Center canvas'],['outside','Outside / walking route']], settings.merchant_placement) + '</select></label>' +
      '</div><div class="mg-merchant-route-preview"><span></span><span></span><span></span><span></span><span></span></div><section class="mg-merchant-settings-actions"><button type="button" data-merchant-place-outside>Move Merchant Outside</button><button type="button" data-merchant-place-center>Return to Center</button></section></section>' + renderCompactCards(settings);
  }

  function renderHistory(settings) {
    return '<section class="mg-merchant-control-card"><h3>Merchant History</h3><div class="mg-merchant-history-head"><label>Window<select name="history_window">' + options([['24h','Last 24 hours'],['7d','Last 7 days'],['30d','Last 30 days']], settings.history_window) + '</select></label></div><div class="mg-merchant-history-list">' + historyItems().map(function (item) { return '<article><strong>' + esc(item.title) + '</strong><span>' + esc(item.copy) + '</span><small>' + esc(item.time) + '</small></article>'; }).join('') + '</div></section>' + renderCompactCards(settings);
  }

  function renderCompactCards(settings) {
    return '<section class="mg-merchant-compact-grid"><article><header><strong>Walk Around Feature</strong><span>' + (settings.walk_enabled ? 'Enabled' : 'Disabled') + '</span></header><p>Merchant can roam within assigned route.</p><div class="mg-merchant-mini-route"><i></i><i></i><i></i><i></i></div></article><article><header><strong>Attached Campaigns (' + activeCampaigns(settings).length + ')</strong><span>›</span></header>' + activeCampaigns(settings).slice(0, 3).map(function (id) { var campaign = campaignList().find(function (item) { return String(item.id) === String(id); }) || { title: id }; return '<p class="mg-merchant-pill">' + esc(campaign.title) + '<b>Active</b></p>'; }).join('') + '</article><article><header><strong>History Snapshot</strong><span>' + esc(settings.history_window) + '</span></header><p><b>Conversations</b><strong>27</strong></p><p><b>Rewards Given</b><strong>12</strong></p><p><b>Handoffs</b><strong>4</strong></p></article></section>';
  }

  function historyItems() {
    return [
      { title: 'Conversation auto-ended', copy: 'Customer reached idle timeout after merchant response.', time: 'Today' },
      { title: 'Reward context delivered', copy: 'Merchant attached Join Newsletter campaign to chat.', time: 'Today' },
      { title: 'Human handoff offered', copy: 'Customer asked for live help after 6 turns.', time: 'Yesterday' },
      { title: 'Walk around route enabled', copy: 'Merchant avatar moved into assigned route mode.', time: 'This week' }
    ];
  }

  function preview() {
    var form = drawer ? drawer.querySelector('[data-merchant-settings-form]') : null;
    if (!form) return;
    var settings = readForm();
    var previewNode = drawer.querySelector('[data-merchant-behavior-preview]');
    if (previewNode) previewNode.innerHTML = '<strong>' + esc(title(settings)) + '</strong><p>' + esc(settings.greeting_message) + '</p>';
  }

  function field(form, name) { return form && form.elements ? form.elements[name] : null; }
  function value(form, name, fallback) { var item = field(form, name); return item ? item.value : fallback; }
  function checked(form, name, fallback) { var item = field(form, name); return item ? (item.checked ? 1 : 0) : fallback; }

  function readForm() {
    var settings = load();
    var form = drawer ? drawer.querySelector('[data-merchant-settings-form]') : null;
    if (!form) return settings;
    settings.interaction_mode = value(form, 'interaction_mode', settings.interaction_mode);
    settings.response_tone = value(form, 'response_tone', settings.response_tone);
    settings.greeting_message = value(form, 'greeting_message', settings.greeting_message).trim();
    settings.handoff_behavior = value(form, 'handoff_behavior', settings.handoff_behavior);
    settings.trigger_reaction = value(form, 'trigger_reaction', settings.trigger_reaction);
    settings.idle_timeout_seconds = intVal(value(form, 'idle_timeout_seconds', settings.idle_timeout_seconds), settings.idle_timeout_seconds);
    settings.max_turns = intVal(value(form, 'max_turns', settings.max_turns), settings.max_turns);
    settings.pause_after_seconds = intVal(value(form, 'pause_after_seconds', settings.pause_after_seconds), settings.pause_after_seconds);
    settings.closing_message = value(form, 'closing_message', settings.closing_message).trim();
    settings.movement_mode = value(form, 'movement_mode', settings.movement_mode);
    settings.route_style = value(form, 'route_style', settings.route_style);
    settings.merchant_placement = value(form, 'merchant_placement', settings.merchant_placement);
    settings.history_window = value(form, 'history_window', settings.history_window);
    settings.auto_greet = checked(form, 'auto_greet', settings.auto_greet);
    settings.recommend_campaigns = checked(form, 'recommend_campaigns', settings.recommend_campaigns);
    settings.allow_end_conversation = checked(form, 'allow_end_conversation', settings.allow_end_conversation);
    settings.end_on_idle = checked(form, 'end_on_idle', settings.end_on_idle);
    settings.end_on_max_turns = checked(form, 'end_on_max_turns', settings.end_on_max_turns);
    settings.end_after_reward = checked(form, 'end_after_reward', settings.end_after_reward);
    settings.end_when_customer_leaves = checked(form, 'end_when_customer_leaves', settings.end_when_customer_leaves);
    settings.walk_enabled = checked(form, 'walk_enabled', settings.walk_enabled);
    var campaignInputs = Array.from(form.querySelectorAll('input[name="attached_campaigns"]'));
    if (campaignInputs.length) settings.attached_campaigns = campaignInputs.filter(function (input) { return input.checked; }).map(function (input) { return input.value; });
    return normalize(settings);
  }

  function applyMovement(settings) {
    settings = normalize(settings);
    merchant.classList.toggle('is-walk-around-enabled', !!settings.walk_enabled);
    merchant.classList.toggle('is-walk-outside', settings.merchant_placement === 'outside');
    if (settings.merchant_placement === 'outside') {
      merchant.style.left = 'calc(50% + 120px)';
      merchant.style.top = 'calc(50% - 78px)';
      merchant.style.transform = 'translate(-50%,-50%)';
    } else {
      merchant.style.left = '';
      merchant.style.top = '';
      merchant.style.transform = '';
    }
    if (roamTimer) { window.clearInterval(roamTimer); roamTimer = null; }
    if (settings.walk_enabled && settings.merchant_placement === 'outside') {
      var route = [[120, -78], [58, -24], [112, 38], [180, 18], [150, -54]];
      roamTimer = window.setInterval(function () {
        if (!merchant.isConnected || !root.isConnected) { window.clearInterval(roamTimer); roamTimer = null; return; }
        var point = route[roamStep % route.length];
        roamStep += 1;
        merchant.style.left = 'calc(50% + ' + point[0] + 'px)';
        merchant.style.top = 'calc(50% + ' + point[1] + 'px)';
      }, 5200);
    }
  }

  function conversationStore() {
    try { return JSON.parse(window.localStorage.getItem(conversationKey) || '{}') || {}; } catch (error) { return {}; }
  }

  function saveConversationStore(store) {
    try { window.localStorage.setItem(conversationKey, JSON.stringify(store || {})); } catch (error) {}
  }

  function recordTurn(sessionId, role) {
    var store = conversationStore();
    sessionId = String(sessionId || 'default');
    var item = store[sessionId] || { turns: 0, last_activity_at: Date.now(), reward_delivered: false, customer_inside: true };
    item.turns += 1;
    item.last_role = role || 'merchant';
    item.last_activity_at = Date.now();
    store[sessionId] = item;
    saveConversationStore(store);
    return item;
  }

  function shouldDisengage(context) {
    var settings = load();
    context = Object.assign({ turns: 0, last_activity_at: Date.now(), reward_delivered: false, customer_inside: true }, context || {});
    var idleMs = Date.now() - Number(context.last_activity_at || Date.now());
    if (settings.end_when_customer_leaves && context.customer_inside === false) return { should_end: true, reason: 'customer_left_area', closing_message: settings.closing_message };
    if (settings.end_after_reward && context.reward_delivered) return { should_end: true, reason: 'reward_delivered', closing_message: settings.closing_message };
    if (settings.end_on_max_turns && Number(context.turns || 0) >= settings.max_turns) return { should_end: true, reason: 'max_turns', closing_message: settings.closing_message };
    if (settings.end_on_idle && idleMs >= settings.idle_timeout_seconds * 1000) return { should_end: true, reason: 'idle_timeout', closing_message: settings.closing_message };
    return { should_end: false, reason: '', closing_message: '' };
  }

  merchant.setAttribute('role', 'button');
  merchant.setAttribute('tabindex', '0');
  merchant.setAttribute('title', 'Open merchant avatar control center');
  merchant.addEventListener('click', function (event) {
    if (event.target.closest('a,button,input,select,textarea,label')) return;
    openDrawer();
  });
  merchant.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDrawer(); }
  });

  window.Microgifter.merchantCanvasBehavior = { get: load, set: function (value) { return save(Object.assign(load(), value || {})); }, open: openDrawer };
  window.Microgifter.merchantConversationPolicy = { get: load, shouldDisengage: shouldDisengage, recordTurn: recordTurn, reset: function (sessionId) { var store = conversationStore(); delete store[String(sessionId || 'default')]; saveConversationStore(store); } };
  save(load());
  loadCampaigns();
})(window, document);
