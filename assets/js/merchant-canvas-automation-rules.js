window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.post || !MG.get) return;

  var map = root.querySelector('[data-canvas-map]');
  var customerLayer = root.querySelector('[data-canvas-customers]');
  var triggerLayer = root.querySelector('[data-canvas-triggers]');
  var layer = triggerLayer || customerLayer;
  if (!map || !customerLayer || !layer) return;

  var settings = {};
  var stampPreview = { stamp_cost: 1, balance: 0, can_auto_message: false };
  var loaded = false;
  var saving = new Set();
  var originalPost = MG.post.bind(MG);
  var legacyDisableNotified = false;

  function payload(r) { return r && r.data ? r.data : r; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function zoneId(el) { return el ? String(el.dataset.canvasTriggerZone || '') : ''; }
  function toast(m, t) { if (MG.toast) MG.toast(m, t || 'info'); }
  function readNumber(text) { var value = String(text || '').replace(/[^0-9]/g, ''); return value ? parseInt(value, 10) || 0 : 0; }
  function behaviorScore(stats) { return Math.max(0, Math.min(100, Math.round(15 + Number(stats.visits || 0) * 10 + Number(stats.messages || 0) * 7 + Number(stats.rewards || 0) * 11 + Number(stats.claims || 0) * 18 + Number(stats.minutes || 0) / 2))); }

  function defaults(id) {
    return Object.assign({ id: id, automation_action: 'message_and_reward', cooldown_policy: 'fifteen_minutes', auto_message_text: '', fallback_action: 'notify_only', crm_segment_name: '', notify_merchant: true }, settings[id] || {});
  }

  function actionOptions(selected) {
    var labels = { message_and_reward: 'Message + Reward', message_only: 'Message only', reward_only: 'Reward only', notify_only: 'Notify merchant only', follow_up: 'Add follow-up', crm_segment: 'Add to CRM segment', analytics_only: 'Analytics only' };
    return Object.keys(labels).map(function (key) { return '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' + labels[key] + '</option>'; }).join('');
  }

  function cooldownOptions(selected) {
    var labels = { five_minutes: '5 min', fifteen_minutes: '15 min', one_hour: '1 hour', once_per_visit: 'Once / visit', once_per_customer_day: 'Once / customer / day' };
    return Object.keys(labels).map(function (key) { return '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' + labels[key] + '</option>'; }).join('');
  }

  function fallbackOptions(selected) {
    var labels = { notify_only: 'Fallback: notify', analytics_only: 'Fallback: analytics', skip: 'Fallback: skip' };
    return Object.keys(labels).map(function (key) { return '<option value="' + key + '"' + (selected === key ? ' selected' : '') + '>' + labels[key] + '</option>'; }).join('');
  }

  function stampText() {
    var cost = Number(stampPreview.stamp_cost || 1);
    var balance = Number(stampPreview.balance || 0);
    if (!stampPreview.schema_ready) return 'Stamp preview unavailable.';
    return 'Auto message cost: ' + cost + ' Stamp · Balance: ' + balance + (balance >= cost ? ' · ready' : ' · fallback will apply');
  }

  function disableLegacyMotionTriggers() {
    root.querySelectorAll('[data-canvas-add-trigger]:not([data-persistent-trigger-button]), [data-canvas-trigger-zone="in_out_box_zone"]').forEach(function (el) {
      el.remove();
    });
  }

  function inject(el) {
    if (!el || el.querySelector('[data-trigger-automation-controls]')) return;
    var id = zoneId(el);
    var cfg = defaults(id);
    var copy = el.querySelector('.mg-canvas-trigger-zone-copy');
    if (!copy) return;
    var block = document.createElement('span');
    block.className = 'mg-canvas-trigger-automation-controls';
    block.setAttribute('data-trigger-automation-controls', '');
    block.innerHTML =
      '<span class="mg-canvas-trigger-row mg-canvas-trigger-row-actions">' +
        '<label>Action<select data-trigger-action>' + actionOptions(cfg.automation_action) + '</select></label>' +
        '<label>Cooldown<select data-trigger-cooldown>' + cooldownOptions(cfg.cooldown_policy) + '</select></label>' +
      '</span>' +
      '<span class="mg-canvas-trigger-row mg-canvas-trigger-row-actions">' +
        '<label>Fallback<select data-trigger-fallback>' + fallbackOptions(cfg.fallback_action) + '</select></label>' +
        '<label class="mg-canvas-trigger-notify"><input data-trigger-notify type="checkbox"' + (cfg.notify_merchant ? ' checked' : '') + '> Notify</label>' +
      '</span>' +
      '<label class="mg-canvas-trigger-message">Message<input data-trigger-message type="text" maxlength="1000" placeholder="Use default automated message" value="' + esc(cfg.auto_message_text || '') + '"></label>' +
      '<label class="mg-canvas-trigger-segment">Segment<input data-trigger-segment type="text" maxlength="160" placeholder="Optional CRM segment" value="' + esc(cfg.crm_segment_name || '') + '"></label>' +
      '<em data-trigger-stamp-preview>' + esc(stampText()) + '</em>';
    copy.appendChild(block);
  }

  function hydrate(el) {
    if (!el) return;
    var cfg = defaults(zoneId(el));
    var action = el.querySelector('[data-trigger-action]');
    var cooldown = el.querySelector('[data-trigger-cooldown]');
    var fallback = el.querySelector('[data-trigger-fallback]');
    var message = el.querySelector('[data-trigger-message]');
    var segment = el.querySelector('[data-trigger-segment]');
    var notify = el.querySelector('[data-trigger-notify]');
    var preview = el.querySelector('[data-trigger-stamp-preview]');
    if (action) action.innerHTML = actionOptions(cfg.automation_action);
    if (cooldown) cooldown.innerHTML = cooldownOptions(cfg.cooldown_policy);
    if (fallback) fallback.innerHTML = fallbackOptions(cfg.fallback_action);
    if (message && document.activeElement !== message) message.value = cfg.auto_message_text || '';
    if (segment && document.activeElement !== segment) segment.value = cfg.crm_segment_name || '';
    if (notify) notify.checked = !!cfg.notify_merchant;
    if (preview) preview.textContent = stampText();
  }

  function syncControls() {
    disableLegacyMotionTriggers();
    Array.from(layer.querySelectorAll('[data-canvas-persistent-zone]')).forEach(function (el) { inject(el); hydrate(el); });
    tagBehaviorScores();
  }

  async function loadSettings() {
    try {
      var data = payload(await MG.get('/api/merchant-canvas/trigger-automation-settings.php')) || {};
      settings = data.zones || {};
      stampPreview = data.stamp_preview || stampPreview;
      loaded = true;
      syncControls();
    } catch (error) { loaded = true; syncControls(); }
  }

  function collect(el) {
    var id = zoneId(el);
    var current = defaults(id);
    return { id: id, automation_action: (el.querySelector('[data-trigger-action]') || {}).value || current.automation_action, cooldown_policy: (el.querySelector('[data-trigger-cooldown]') || {}).value || current.cooldown_policy, fallback_action: (el.querySelector('[data-trigger-fallback]') || {}).value || current.fallback_action, auto_message_text: (el.querySelector('[data-trigger-message]') || {}).value || '', crm_segment_name: (el.querySelector('[data-trigger-segment]') || {}).value || '', notify_merchant: !!((el.querySelector('[data-trigger-notify]') || {}).checked) };
  }

  async function save(el) {
    var data = collect(el);
    if (!data.id || data.id.indexOf('tmp-') === 0 || saving.has(data.id)) return;
    settings[data.id] = Object.assign({}, settings[data.id] || {}, data);
    saving.add(data.id);
    try {
      var result = payload(await originalPost('/api/merchant-canvas/trigger-zone-automation-save.php', data)) || {};
      settings[data.id] = Object.assign({}, settings[data.id] || {}, result);
      toast('Automation saved.', 'success');
    } catch (error) { toast(error.message || 'Unable to save automation.', 'error'); }
    finally { saving.delete(data.id); hydrate(el); }
  }

  function tagBehaviorScores() {
    Array.from(customerLayer.querySelectorAll('[data-session-id]')).forEach(function (card) {
      if (!card.querySelector('.mg-canvas-avatar-score')) {
        var minutes = card.textContent.indexOf('hr') !== -1 ? readNumber(card.textContent) * 60 : readNumber(card.textContent);
        var badge = document.createElement('span');
        badge.className = 'mg-canvas-avatar-score';
        badge.textContent = 'Score ' + behaviorScore({ minutes: minutes });
        card.appendChild(badge);
      }
    });
    var grid = root.querySelector('.mg-canvas-crm-grid');
    if (grid && !grid.querySelector('[data-behavior-score-card]')) {
      var stats = { visits: 0, messages: 0, rewards: 0, claims: 0 };
      Array.from(grid.querySelectorAll('.mg-canvas-crm-stat')).forEach(function (card) {
        var label = ((card.querySelector('span') || {}).textContent || '').toLowerCase();
        var value = readNumber((card.querySelector('strong') || {}).textContent || '0');
        if (label.indexOf('visits') !== -1) stats.visits = value;
        if (label.indexOf('messages') !== -1) stats.messages = value;
        if (label.indexOf('rewards') !== -1) stats.rewards = value;
        if (label.indexOf('claims') !== -1) stats.claims = value;
      });
      var score = behaviorScore(stats);
      var article = document.createElement('article');
      article.className = 'mg-canvas-crm-stat is-score';
      article.setAttribute('data-behavior-score-card', '');
      article.innerHTML = '<span>Behavior score</span><strong>' + score + '</strong>';
      grid.insertBefore(article, grid.firstChild);
      var summary = root.querySelector('.mg-canvas-customer-summary div');
      if (summary && !summary.querySelector('.mg-canvas-score-pill')) {
        var pill = document.createElement('span');
        pill.className = 'mg-canvas-score-pill';
        pill.textContent = 'Live behavior score ' + score;
        summary.appendChild(pill);
      }
    }
  }

  MG.post = function (url, data, options) {
    if (String(url || '') === '/api/merchant-canvas/campaign-trigger.php') {
      if (data && data.trigger_zone_id) return originalPost('/api/merchant-canvas/campaign-trigger-automation.php', data || {}, options);
      if (!legacyDisableNotified) {
        legacyDisableNotified = true;
        window.setTimeout(function () { legacyDisableNotified = false; }, 60000);
      }
      return Promise.resolve({ data: { triggered: false, skipped: true, legacy_trigger_disabled: true, message: 'Legacy local trigger ignored. Persistent trigger zones own automation rules.' } });
    }
    return originalPost(url, data, options);
  };

  layer.addEventListener('change', function (event) { if (!event.target.closest('[data-trigger-automation-controls]')) return; var el = event.target.closest('[data-canvas-persistent-zone]'); if (el) save(el); });
  layer.addEventListener('focusout', function (event) { if (!event.target.matches('[data-trigger-message],[data-trigger-segment]')) return; var el = event.target.closest('[data-canvas-persistent-zone]'); if (el) save(el); });

  var observer = new MutationObserver(syncControls);
  observer.observe(layer, { childList: true, subtree: true });
  new MutationObserver(function () { disableLegacyMotionTriggers(); tagBehaviorScores(); }).observe(root, { childList: true, subtree: true });
  loadSettings();
  disableLegacyMotionTriggers();
  window.setInterval(syncControls, 1200);
})(window, document);
