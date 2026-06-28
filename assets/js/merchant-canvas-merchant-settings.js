window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root) return;
  var merchant = root.querySelector('.mg-canvas-merchant-node');
  if (!merchant) return;

  var key = 'mgCanvasMerchantBehavior:v1';
  var drawer = null;
  var defaults = {
    interaction_mode: 'guided',
    response_tone: 'warm_professional',
    greeting_message: 'Welcome in. I can help with rewards, offers, or questions while you are browsing.',
    auto_greet: 1,
    recommend_campaigns: 1,
    handoff_behavior: 'offer_handoff',
    trigger_reaction: 'message_when_triggered'
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function boolInt(value) { return value === true || value === 1 || value === '1' || value === 'true' || value === 'on' ? 1 : 0; }
  function load() {
    var stored = {};
    try { stored = JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (error) { stored = {}; }
    stored = Object.assign({}, defaults, stored);
    stored.auto_greet = boolInt(stored.auto_greet);
    stored.recommend_campaigns = boolInt(stored.recommend_campaigns);
    return stored;
  }
  function save(settings) {
    settings = Object.assign({}, defaults, settings || {});
    settings.auto_greet = boolInt(settings.auto_greet);
    settings.recommend_campaigns = boolInt(settings.recommend_campaigns);
    try { window.localStorage.setItem(key, JSON.stringify(settings)); } catch (error) {}
    merchant.dataset.interactionMode = settings.interaction_mode;
    merchant.dataset.responseTone = settings.response_tone;
    merchant.dataset.autoGreet = String(settings.auto_greet);
    root.dataset.merchantInteractionMode = settings.interaction_mode;
    root.dataset.merchantAutoGreet = String(settings.auto_greet);
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
    return 'Guided helper';
  }
  function ensureDrawer() {
    if (drawer && drawer.isConnected) return drawer;
    drawer = document.createElement('aside');
    drawer.className = 'mg-canvas-merchant-settings-drawer';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.innerHTML = '<div class="mg-merchant-settings-head"><div><span>Merchant Avatar</span><h2>Store behavior settings</h2></div><button type="button" data-merchant-settings-close aria-label="Close merchant avatar settings">×</button></div><div class="mg-merchant-settings-body" data-merchant-settings-body></div><div class="mg-merchant-settings-foot"><p class="mg-merchant-settings-status" data-merchant-settings-status role="status"></p><button type="button" class="mg-btn mg-btn-primary" data-merchant-settings-save>Save Settings</button></div>';
    document.body.appendChild(drawer);
    drawer.addEventListener('click', function (event) {
      if (event.target.closest('[data-merchant-settings-close]')) closeDrawer();
      if (event.target.closest('[data-merchant-settings-save]')) { save(readForm()); status('Saved.', 'success'); render(); }
    });
    drawer.addEventListener('change', function () { save(readForm()); status('Saved.', 'success'); render(); });
    drawer.addEventListener('input', function () { save(readForm()); status('Saved.', 'success'); preview(); });
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
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    merchant.classList.remove('is-settings-open');
  }
  function render() {
    var settings = load();
    var body = ensureDrawer().querySelector('[data-merchant-settings-body]');
    body.innerHTML = '<form class="mg-merchant-settings-form" data-merchant-settings-form>' +
      '<section class="mg-merchant-settings-summary"><article><span>Mode</span><strong>' + esc(settings.interaction_mode.replace(/_/g, ' ')) + '</strong></article><article><span>Auto greet</span><strong>' + (settings.auto_greet ? 'On' : 'Off') + '</strong></article><article><span>Tone</span><strong>' + esc(settings.response_tone.replace(/_/g, ' ')) + '</strong></article></section>' +
      '<section class="mg-merchant-behavior-preview" data-merchant-behavior-preview><strong>' + esc(title(settings)) + '</strong><p>' + esc(settings.greeting_message) + '</p></section>' +
      '<div class="mg-merchant-settings-row"><label>Interaction mode<select name="interaction_mode">' + options([['guided','Guided helper'],['proactive','Proactive helper'],['observe_only','Observe only'],['manual_first','Manual first']], settings.interaction_mode) + '</select></label><label>Response tone<select name="response_tone">' + options([['warm_professional','Warm professional'],['concise','Concise'],['hospitality','Hospitality'],['sales_assist','Sales assist'],['premium','Premium']], settings.response_tone) + '</select></label></div>' +
      '<label>Greeting message<textarea name="greeting_message" rows="5" maxlength="1000">' + esc(settings.greeting_message) + '</textarea><small>Used when the merchant avatar greets new shoppers.</small></label>' +
      '<div class="mg-merchant-settings-row"><label>Human handoff<select name="handoff_behavior">' + options([['offer_handoff','Offer handoff when asked'],['always_offer','Always offer handoff'],['never_offer','Never offer handoff automatically']], settings.handoff_behavior) + '</select></label><label>Trigger reaction<select name="trigger_reaction">' + options([['message_when_triggered','Message when triggered'],['reward_context','Reward context'],['analytics_only','Analytics only']], settings.trigger_reaction) + '</select></label></div>' +
      '<label class="mg-merchant-settings-check"><input type="checkbox" name="auto_greet" value="1"' + (settings.auto_greet ? ' checked' : '') + '> Automatically greet new customers inside the canvas</label>' +
      '<label class="mg-merchant-settings-check"><input type="checkbox" name="recommend_campaigns" value="1"' + (settings.recommend_campaigns ? ' checked' : '') + '> Mention relevant campaigns and rewards when helpful</label>' +
      '</form>';
  }
  function preview() {
    var form = drawer ? drawer.querySelector('[data-merchant-settings-form]') : null;
    var node = drawer ? drawer.querySelector('[data-merchant-behavior-preview]') : null;
    if (!form || !node) return;
    var settings = readForm();
    node.innerHTML = '<strong>' + esc(title(settings)) + '</strong><p>' + esc(settings.greeting_message) + '</p>';
  }
  function readForm() {
    var form = drawer ? drawer.querySelector('[data-merchant-settings-form]') : null;
    if (!form) return load();
    return {
      interaction_mode: form.elements.interaction_mode ? form.elements.interaction_mode.value : defaults.interaction_mode,
      response_tone: form.elements.response_tone ? form.elements.response_tone.value : defaults.response_tone,
      greeting_message: form.elements.greeting_message ? form.elements.greeting_message.value.trim() : defaults.greeting_message,
      auto_greet: form.elements.auto_greet && form.elements.auto_greet.checked ? 1 : 0,
      recommend_campaigns: form.elements.recommend_campaigns && form.elements.recommend_campaigns.checked ? 1 : 0,
      handoff_behavior: form.elements.handoff_behavior ? form.elements.handoff_behavior.value : defaults.handoff_behavior,
      trigger_reaction: form.elements.trigger_reaction ? form.elements.trigger_reaction.value : defaults.trigger_reaction
    };
  }

  merchant.setAttribute('role', 'button');
  merchant.setAttribute('tabindex', '0');
  merchant.setAttribute('title', 'Open merchant avatar behavior settings');
  merchant.addEventListener('click', function (event) {
    if (event.target.closest('a,button,input,select,textarea,label')) return;
    openDrawer();
  });
  merchant.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDrawer(); }
  });

  window.Microgifter.merchantCanvasBehavior = { get: load, set: function (value) { return save(Object.assign(load(), value || {})); }, open: openDrawer };
  save(load());
})(window, document);
