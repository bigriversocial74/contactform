window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.post) return;
  var map = root.querySelector('[data-canvas-map]');
  var layer = root.querySelector('[data-canvas-customers]');
  if (!map || !layer) return;

  var storageKey = 'mgStoreCanvasTriggerSuite:v1';
  var testMode = false;
  var templateMenu = null;
  var testBar = null;
  var testAvatar = null;
  var activeZoneId = '';
  var localHistory = new Map();

  var templates = [
    ['welcome','Welcome Zone','Message','Greet new shoppers and point them to rewards.','message','new_customers','business_hours','message_only'],
    ['reward','Reward Zone','Reward','Send the assigned campaign reward.','reward','everyone','always','message_and_reward'],
    ['newsletter','Newsletter Signup Zone','Signup','Invite shoppers into a CRM segment.','signup','new_customers','always','crm_segment'],
    ['vip','VIP Customer Zone','VIP','Handle returning or high-intent customers.','vip','returning_customers','business_hours','follow_up'],
    ['interest','Abandoned Interest Zone','Follow-up','Follow up on unclaimed interest.','followup','unclaimed_rewards','always','follow_up'],
    ['exit','Exit Intent Zone','Alert','Make a final offer before shoppers leave.','alert','everyone','always','message_and_reward'],
    ['staff','Staff Alert Zone','Alert','Notify staff instead of sending a customer reward.','alert','vip_customers','business_hours','notify_only']
  ];

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[char]; }); }
  function payload(response) { return response && response.data ? response.data : response; }
  function now() { return Date.now(); }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function pretty(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }

  function loadStore() {
    try { return JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; }
    catch (error) { return {}; }
  }
  function saveStore(store) { localStorage.setItem(storageKey, JSON.stringify(store || {})); }
  function getSettings(id) { return Object.assign(defaultSettings(), loadStore()[id] || {}); }
  function setSettings(id, settings) {
    var store = loadStore();
    store[id] = Object.assign(defaultSettings(), store[id] || {}, settings || {});
    saveStore(store);
    return store[id];
  }
  function defaultSettings() {
    return {
      template_id:'reward',
      visual_type:'reward',
      target_rule:'everyone',
      schedule_rule:'always',
      schedule_days:'mon,tue,wed,thu,fri,sat,sun',
      schedule_start:'09:00',
      schedule_end:'17:00',
      score:10
    };
  }
  function templateById(id) {
    return templates.find(function (item) { return item[0] === id; }) || templates[1];
  }
  function canvasRect() { return map.getBoundingClientRect(); }
  function relRect(node) {
    var rect = node.getBoundingClientRect();
    var box = canvasRect();
    return { x:rect.left - box.left, y:rect.top - box.top, width:rect.width, height:rect.height };
  }
  function overlaps(a, b, pad) {
    pad = pad || 0;
    return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y;
  }
  function fmt(value) {
    var raw = String(value || '').trim();
    if (!raw) return 'Never';
    return raw.replace('T', ' ').slice(0, 19);
  }

  function activeTriggerNode() {
    return layer.querySelector('.mg-canvas-trigger-zone.is-settings-open') || layer.querySelector('[data-canvas-persistent-zone]');
  }
  function currentZoneId() {
    var node = activeTriggerNode();
    return node ? String(node.dataset.canvasTriggerZone || '') : '';
  }
  function triggerNodes() {
    return Array.from(layer.querySelectorAll('[data-canvas-persistent-zone]'));
  }

  function templateDefaults(id) {
    var template = templateById(id);
    return {
      template_id:template[0],
      name:template[1],
      visual_type:template[4],
      target_rule:template[5],
      schedule_rule:template[6],
      automation_action:template[7],
      auto_message_text:'Hi {first_name}, you entered the ' + template[1] + '. I can help with {campaign_title}.'
    };
  }

  function placeForNewZone() {
    var used = triggerNodes().map(relRect);
    var box = canvasRect();
    var width = 17;
    var height = 10;
    var candidates = [];
    [8, 28, 48, 68].forEach(function (x) {
      [10, 28, 46, 64, 78].forEach(function (y) { candidates.push({x:x,y:y,width:width,height:height}); });
    });
    for (var i = 0; i < candidates.length; i++) {
      var c = candidates[i];
      var px = { x:box.width * c.x / 100, y:box.height * c.y / 100, width:box.width * c.width / 100, height:box.height * c.height / 100 };
      if (!used.some(function (u) { return overlaps(px, u, 24); })) return c;
    }
    return candidates[triggerNodes().length % candidates.length] || {x:12,y:12,width:17,height:10};
  }

  function ensureTemplateMenu() {
    if (templateMenu && templateMenu.isConnected) return templateMenu;
    templateMenu = document.createElement('aside');
    templateMenu.className = 'mg-canvas-trigger-template-menu';
    templateMenu.setAttribute('aria-hidden', 'true');
    templateMenu.innerHTML = '<header><strong>Create Trigger</strong><button type="button" data-suite-template-close>x</button></header><div class="mg-trigger-template-grid">' + templates.map(function (template) {
      return '<button type="button" data-suite-template="' + esc(template[0]) + '"><strong>' + esc(template[1]) + '</strong><small>' + esc(template[3]) + '</small><b>' + esc(template[2]) + '</b></button>';
    }).join('') + '</div>';
    map.appendChild(templateMenu);
    templateMenu.addEventListener('click', function (event) {
      if (event.target.closest('[data-suite-template-close]')) return closeTemplateMenu();
      var button = event.target.closest('[data-suite-template]');
      if (!button) return;
      closeTemplateMenu();
      createFromTemplate(button.dataset.suiteTemplate);
    });
    return templateMenu;
  }

  function openTemplateMenu() {
    var menu = ensureTemplateMenu();
    menu.classList.add('is-open');
    menu.setAttribute('aria-hidden', 'false');
  }
  function closeTemplateMenu() {
    if (!templateMenu) return;
    templateMenu.classList.remove('is-open');
    templateMenu.setAttribute('aria-hidden', 'true');
  }

  function createFromTemplate(templateId) {
    var defaults = templateDefaults(templateId);
    var place = placeForNewZone();
    var payloadData = {
      name:defaults.name,
      trigger_key:'store_canvas_' + defaults.template_id + '_' + Date.now(),
      campaign_id:'',
      priority:3,
      x:place.x,
      y:place.y,
      width:place.width,
      height:place.height,
      status:'active',
      automation_action:defaults.automation_action,
      cooldown_policy:'fifteen_minutes',
      cooldown_seconds:900,
      auto_message_text:defaults.auto_message_text,
      fallback_action:'notify_only',
      crm_segment_name:defaults.template_id === 'newsletter' ? 'Newsletter Signup' : '',
      notify_merchant:1
    };
    MG.post('/api/merchant-canvas/trigger-zone-save.php', payloadData).then(function (response) {
      var data = payload(response) || {};
      var id = data.zone && data.zone.id ? String(data.zone.id) : '';
      if (id) setSettings(id, defaults);
      toast('Trigger template created.', 'success');
      window.setTimeout(function () { window.location.reload(); }, 350);
    }).catch(function (error) {
      toast(error.message || 'Unable to create trigger template.', 'error');
    });
  }

  function ensureTestBar() {
    if (testBar && testBar.isConnected) return testBar;
    testBar = document.createElement('div');
    testBar.className = 'mg-canvas-test-mode-bar';
    testBar.innerHTML = '<button type="button" data-suite-test-toggle>Test Mode: Off</button><button type="button" data-suite-test-run disabled>Run Test</button>';
    map.appendChild(testBar);
    testBar.addEventListener('click', function (event) {
      if (event.target.closest('[data-suite-test-toggle]')) toggleTestMode();
      if (event.target.closest('[data-suite-test-run]')) runTest();
    });
    return testBar;
  }
  function toggleTestMode() {
    testMode = !testMode;
    root.classList.toggle('is-trigger-test-mode', testMode);
    var toggle = ensureTestBar().querySelector('[data-suite-test-toggle]');
    var run = ensureTestBar().querySelector('[data-suite-test-run]');
    if (toggle) toggle.textContent = testMode ? 'Test Mode: On' : 'Test Mode: Off';
    if (run) run.disabled = !testMode;
    if (testMode) ensureTestAvatar();
    else if (testAvatar) testAvatar.remove();
  }
  function ensureTestAvatar() {
    if (testAvatar && testAvatar.isConnected) return testAvatar;
    testAvatar = document.createElement('article');
    testAvatar.className = 'mg-canvas-test-avatar';
    testAvatar.innerHTML = '<strong>Test Customer</strong><span>no live send</span>';
    testAvatar.style.left = '42%';
    testAvatar.style.top = '52%';
    layer.appendChild(testAvatar);
    return testAvatar;
  }
  function runTest() {
    var node = activeTriggerNode();
    if (!node) return toast('Create or select a trigger first.', 'info');
    var avatar = ensureTestAvatar();
    var rect = relRect(node);
    avatar.style.left = Math.round(rect.x + rect.width / 2 - 56) + 'px';
    avatar.style.top = Math.round(rect.y + rect.height / 2 - 20) + 'px';
    simulateHit(node, true);
  }

  function simulateHit(node, explicit) {
    var id = node ? String(node.dataset.canvasTriggerZone || '') : '';
    if (!id) return;
    addHistory(id, { created_at:new Date().toISOString(), customer_name:'Test Customer', campaign_title:node.querySelector('[data-zone-campaign]') ? node.querySelector('[data-zone-campaign]').textContent : 'Test campaign', test:true });
    node.classList.add('is-hot');
    window.setTimeout(function () { node.classList.remove('is-hot'); }, 2200);
    if (explicit) toast('Test trigger passed. No live message or reward was sent.', 'success');
    renderHistory(id);
  }

  function addHistory(id, item) {
    if (!localHistory.has(id)) localHistory.set(id, []);
    var list = localHistory.get(id);
    list.unshift(item);
    localHistory.set(id, list.slice(0, 10));
  }

  function isScheduleActive(settings) {
    if (!settings || settings.schedule_rule === 'always' || settings.schedule_rule === 'campaign_window') return true;
    var date = new Date();
    var keys = ['sun','mon','tue','wed','thu','fri','sat'];
    var today = keys[date.getDay()];
    if (settings.schedule_rule === 'weekdays' && (today === 'sat' || today === 'sun')) return false;
    if (settings.schedule_rule === 'weekends' && today !== 'sat' && today !== 'sun') return false;
    if (settings.schedule_rule === 'custom') {
      var days = String(settings.schedule_days || '').split(',').map(function (day) { return day.trim(); });
      if (days.indexOf(today) === -1) return false;
    }
    if (settings.schedule_rule === 'business_hours' || settings.schedule_rule === 'custom') {
      var current = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
      return current >= String(settings.schedule_start || '09:00') && current <= String(settings.schedule_end || '17:00');
    }
    return true;
  }

  function scoreTrigger(id) {
    var settings = getSettings(id);
    var node = layer.querySelector('[data-canvas-trigger-zone="' + CSS.escape(id) + '"]');
    var score = 0;
    if (node && node.querySelector('[data-zone-name]') && node.querySelector('[data-zone-name]').textContent.trim()) score++;
    if (node && node.querySelector('[data-zone-campaign]') && node.querySelector('[data-zone-campaign]').textContent.trim() && node.querySelector('[data-zone-campaign]').textContent !== 'No campaign assigned') score++;
    if (settings.template_id) score++;
    if (settings.visual_type) score++;
    if (settings.target_rule) score++;
    if (settings.schedule_rule) score++;
    if (settings.schedule_start && settings.schedule_end) score++;
    if (settings.score >= 10) score++;
    if (node && parseFloat(node.style.width || '0') > 80 && parseFloat(node.style.height || '0') > 50) score++;
    score++;
    return Math.min(10, score);
  }

  function decorateNodes() {
    triggerNodes().forEach(function (node) {
      var id = String(node.dataset.canvasTriggerZone || '');
      if (!id) return;
      var settings = getSettings(id);
      node.classList.add('is-suite-managed');
      node.classList.remove('is-type-message','is-type-reward','is-type-signup','is-type-vip','is-type-followup','is-type-alert','is-type-analytics','is-scheduled-off');
      node.classList.add('is-type-' + String(settings.visual_type || 'reward').replace(/[^a-z0-9_-]/g, ''));
      node.classList.toggle('is-scheduled-off', !isScheduleActive(settings));
      var state = node.querySelector('[data-zone-suite-state]');
      if (!state) {
        var main = node.querySelector('.mg-canvas-trigger-main');
        state = document.createElement('b');
        state.setAttribute('data-zone-suite-state', '');
        if (main) main.appendChild(state);
      }
      state.textContent = !isScheduleActive(settings) ? 'scheduled off' : (node.classList.contains('is-cooldown') ? 'cooling down' : 'active');
    });
  }

  function augmentDrawer() {
    var drawer = document.querySelector('.mg-canvas-trigger-settings-drawer.is-open');
    var form = drawer ? drawer.querySelector('[data-trigger-settings-form]') : null;
    var id = currentZoneId();
    if (!drawer || !form || !id) return;
    activeZoneId = id;
    if (form.querySelector('[data-suite-panel]')) {
      updateSuitePanel(form, id);
      return;
    }
    var settings = getSettings(id);
    var panel = document.createElement('section');
    panel.className = 'mg-trigger-suite-panel';
    panel.setAttribute('data-suite-panel', '');
    panel.innerHTML = suitePanelHtml(settings, id);
    form.insertBefore(panel, form.firstChild);
    var actions = form.querySelector('.mg-trigger-settings-actions');
    if (actions && !actions.querySelector('[data-suite-duplicate]')) {
      actions.insertAdjacentHTML('afterbegin', '<button type="button" data-suite-duplicate>Duplicate Trigger</button><button type="button" data-suite-test-one>Run Test</button>');
    }
    form.addEventListener('change', onSuiteChange, true);
    form.addEventListener('input', onSuiteChange, true);
    form.addEventListener('click', onSuiteClick, true);
    renderHistory(id);
  }

  function suitePanelHtml(settings, id) {
    return '<div class="mg-trigger-score-card"><strong>Build Score ' + scoreTrigger(id) + '/10</strong><span>' + (scoreTrigger(id) >= 10 ? '10/10 production-ready trigger.' : 'Complete template, targeting, schedule, and campaign to reach 10/10.') + '</span></div>' +
      '<div class="mg-trigger-settings-row"><label>Template<select name="suite_template_id">' + templates.map(function (template) { return '<option value="' + esc(template[0]) + '"' + (settings.template_id === template[0] ? ' selected' : '') + '>' + esc(template[1]) + '</option>'; }).join('') + '</select></label><label>Visual type<select name="suite_visual_type">' + optionList([['message','Message zone'],['reward','Reward zone'],['signup','Signup zone'],['vip','VIP zone'],['followup','Follow-up zone'],['alert','Alert zone'],['analytics','Analytics only']], settings.visual_type) + '</select></label></div>' +
      '<div class="mg-trigger-settings-row"><label>Targeting<select name="suite_target_rule">' + optionList([['everyone','Everyone'],['new_customers','New customers'],['returning_customers','Returning customers'],['unclaimed_rewards','Customers with unclaimed rewards'],['campaign_customers','Selected campaign customers'],['vip_customers','VIP / high intent customers']], settings.target_rule) + '</select></label><label>Schedule<select name="suite_schedule_rule">' + optionList([['always','Always active'],['business_hours','Business hours'],['weekdays','Weekdays only'],['weekends','Weekends only'],['campaign_window','Campaign window'],['custom','Custom days + hours']], settings.schedule_rule) + '</select></label></div>' +
      '<div class="mg-trigger-settings-row"><label>Start<input type="time" name="suite_schedule_start" value="' + esc(settings.schedule_start || '09:00') + '"></label><label>End<input type="time" name="suite_schedule_end" value="' + esc(settings.schedule_end || '17:00') + '"></label></div>' +
      '<label>Days<input name="suite_schedule_days" value="' + esc(settings.schedule_days || 'mon,tue,wed,thu,fri,sat,sun') + '"><small>Use mon,tue,wed,thu,fri,sat,sun</small></label>' +
      '<section class="mg-trigger-drawer-history" data-suite-history><strong>Trigger history</strong><span>Loading recent activity...</span></section>';
  }

  function optionList(items, selected) {
    return items.map(function (item) { return '<option value="' + esc(item[0]) + '"' + (String(selected) === String(item[0]) ? ' selected' : '') + '>' + esc(item[1]) + '</option>'; }).join('');
  }

  function updateSuitePanel(form, id) {
    var panel = form.querySelector('[data-suite-panel]');
    var card = panel ? panel.querySelector('.mg-trigger-score-card') : null;
    if (card) card.innerHTML = '<strong>Build Score ' + scoreTrigger(id) + '/10</strong><span>' + (scoreTrigger(id) >= 10 ? '10/10 production-ready trigger.' : 'Complete template, targeting, schedule, and campaign to reach 10/10.') + '</span>';
  }

  function onSuiteChange(event) {
    var form = event.currentTarget;
    var id = currentZoneId();
    if (!id || !event.target.name || event.target.name.indexOf('suite_') !== 0) return;
    var settings = getSettings(id);
    settings.template_id = form.elements.suite_template_id ? form.elements.suite_template_id.value : settings.template_id;
    settings.visual_type = form.elements.suite_visual_type ? form.elements.suite_visual_type.value : settings.visual_type;
    settings.target_rule = form.elements.suite_target_rule ? form.elements.suite_target_rule.value : settings.target_rule;
    settings.schedule_rule = form.elements.suite_schedule_rule ? form.elements.suite_schedule_rule.value : settings.schedule_rule;
    settings.schedule_start = form.elements.suite_schedule_start ? form.elements.suite_schedule_start.value : settings.schedule_start;
    settings.schedule_end = form.elements.suite_schedule_end ? form.elements.suite_schedule_end.value : settings.schedule_end;
    settings.schedule_days = form.elements.suite_schedule_days ? form.elements.suite_schedule_days.value : settings.schedule_days;
    if (event.target.name === 'suite_template_id') {
      var defaults = templateDefaults(settings.template_id);
      settings.visual_type = defaults.visual_type;
      settings.target_rule = defaults.target_rule;
      settings.schedule_rule = defaults.schedule_rule;
      var name = form.elements.name;
      if (name) name.value = defaults.name;
      var action = form.elements.automation_action;
      if (action) action.value = defaults.automation_action;
      var message = form.elements.auto_message_text;
      if (message) message.value = defaults.auto_message_text;
    }
    settings.score = 10;
    setSettings(id, settings);
    decorateNodes();
    updateSuitePanel(form, id);
  }

  function onSuiteClick(event) {
    if (event.target.closest('[data-suite-duplicate]')) {
      event.preventDefault();
      duplicateActiveTrigger();
    }
    if (event.target.closest('[data-suite-test-one]')) {
      event.preventDefault();
      if (!testMode) toggleTestMode();
      runTest();
    }
  }

  function duplicateActiveTrigger() {
    var id = currentZoneId();
    var node = activeTriggerNode();
    var form = document.querySelector('.mg-canvas-trigger-settings-drawer.is-open [data-trigger-settings-form]');
    if (!id || !node || !form) return;
    var box = canvasRect();
    var rect = relRect(node);
    var settings = getSettings(id);
    var payloadData = {
      name:(form.elements.name ? form.elements.name.value : 'Trigger Zone') + ' Copy',
      trigger_key:'store_canvas_copy_' + Date.now(),
      campaign_id:form.elements.campaign_id ? form.elements.campaign_id.value : '',
      priority:form.elements.priority ? form.elements.priority.value : 3,
      x:clamp(((rect.x + 36) / box.width) * 100, 0, 88),
      y:clamp(((rect.y + 36) / box.height) * 100, 0, 88),
      width:clamp((rect.width / box.width) * 100, 4, 100),
      height:clamp((rect.height / box.height) * 100, 4, 100),
      status:'active',
      automation_action:form.elements.automation_action ? form.elements.automation_action.value : 'message_and_reward',
      cooldown_policy:form.elements.cooldown_policy ? form.elements.cooldown_policy.value : 'fifteen_minutes',
      cooldown_seconds:form.elements.cooldown_seconds ? form.elements.cooldown_seconds.value : 900,
      auto_message_text:form.elements.auto_message_text ? form.elements.auto_message_text.value : '',
      fallback_action:form.elements.fallback_action ? form.elements.fallback_action.value : 'notify_only',
      crm_segment_name:form.elements.crm_segment_name ? form.elements.crm_segment_name.value : '',
      notify_merchant:1
    };
    MG.post('/api/merchant-canvas/trigger-zone-save.php', payloadData).then(function (response) {
      var data = payload(response) || {};
      if (data.zone && data.zone.id) setSettings(String(data.zone.id), settings);
      toast('Trigger duplicated.', 'success');
      window.setTimeout(function () { window.location.reload(); }, 350);
    }).catch(function (error) { toast(error.message || 'Unable to duplicate trigger.', 'error'); });
  }

  function renderHistory(id) {
    var panel = document.querySelector('[data-suite-history]');
    if (!panel || !id) return;
    var local = localHistory.get(id) || [];
    panel.innerHTML = '<strong>Trigger history</strong><div class="mg-trigger-history-stats"><article><span>Score</span><strong>' + scoreTrigger(id) + '/10</strong></article><article><span>Local tests</span><strong>' + local.length + '</strong></article><article><span>State</span><strong>' + esc(getNodeStateText(id)) + '</strong></article></div>' + (local.length ? local.map(function (item) { return '<div class="mg-trigger-history-row"><strong>' + esc(item.customer_name || 'Customer') + '</strong><span>' + esc(fmt(item.created_at)) + '</span><small>' + esc(item.campaign_title || 'Trigger event') + '</small><em><b>test</b></em></div>'; }).join('') : '<p>No local test events yet. Use Test Mode or Run Test.</p>');
    if (!MG.get || id.indexOf('tmp-') === 0) return;
    MG.get('/api/merchant-canvas/trigger-zone-analytics.php?zone_id=' + encodeURIComponent(id)).then(function (response) {
      var data = payload(response) || {};
      var stats = data.stats || {};
      var events = Array.isArray(data.events) ? data.events : [];
      var rows = events.slice(0, 5).map(function (event) { return '<div class="mg-trigger-history-row"><strong>' + esc(event.customer_name || 'Customer') + '</strong><span>' + esc(fmt(event.created_at)) + '</span><small>' + esc(event.campaign_title || event.event_label || 'Trigger event') + '</small><em>' + (event.reward_sent ? '<b>reward</b>' : '<b>event</b>') + '</em></div>'; }).join('');
      panel.innerHTML = '<strong>Trigger history</strong><div class="mg-trigger-history-stats"><article><span>Score</span><strong>' + scoreTrigger(id) + '/10</strong></article><article><span>Fires</span><strong>' + esc(stats.fires || 0) + '</strong></article><article><span>Messages</span><strong>' + esc(stats.messages_sent || 0) + '</strong></article><article><span>Rewards</span><strong>' + esc(stats.rewards_sent || 0) + '</strong></article></div>' + (local.length ? local.map(function (item) { return '<div class="mg-trigger-history-row"><strong>' + esc(item.customer_name || 'Customer') + '</strong><span>' + esc(fmt(item.created_at)) + '</span><small>' + esc(item.campaign_title || 'Test event') + '</small><em><b>test</b></em></div>'; }).join('') : '') + (rows || '<p>No live trigger events yet.</p>');
    }).catch(function () {});
  }

  function getNodeStateText(id) {
    var node = layer.querySelector('[data-canvas-trigger-zone="' + CSS.escape(id) + '"]');
    if (!node) return 'Ready';
    if (node.classList.contains('is-scheduled-off')) return 'Scheduled off';
    if (node.classList.contains('is-cooldown')) return 'Cooling down';
    if (node.classList.contains('is-paused')) return 'Paused';
    return 'Active';
  }

  function patchPostForSchedule() {
    if (MG.__triggerSuitePostPatched || !MG.post) return;
    var original = MG.post;
    MG.post = function (url, data) {
      var endpoint = String(url || '');
      if (endpoint.indexOf('/api/merchant-canvas/campaign-trigger') !== -1 && data && data.trigger_zone_id) {
        var settings = getSettings(String(data.trigger_zone_id));
        if (!isScheduleActive(settings)) {
          return Promise.resolve({ data:{ skipped:true, schedule_off:true }, message:'Trigger schedule is inactive.' });
        }
      }
      return original.apply(this, arguments);
    };
    MG.__triggerSuitePostPatched = true;
  }

  function wireCreateButton() {
    map.addEventListener('click', function (event) {
      var button = event.target.closest('[data-persistent-trigger-button]');
      if (!button) return;
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) event.stopImmediatePropagation();
      openTemplateMenu();
    }, true);
  }

  function observe() {
    var observer = new MutationObserver(function () {
      decorateNodes();
      augmentDrawer();
    });
    observer.observe(document.body, { childList:true, subtree:true, attributes:true, attributeFilter:['class','style','data-canvas-trigger-zone'] });
    window.setInterval(function () { decorateNodes(); augmentDrawer(); }, 900);
  }

  function init() {
    ensureTemplateMenu();
    ensureTestBar();
    wireCreateButton();
    patchPostForSchedule();
    observe();
    decorateNodes();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once:true });
  else init();
})(window, document);
