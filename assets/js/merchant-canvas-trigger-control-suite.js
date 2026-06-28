window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.post) return;
  var map = root.querySelector('[data-canvas-map]');
  var customerLayer = root.querySelector('[data-canvas-customers]');
  if (!map || !customerLayer) return;

  var storageKey = 'mgStoreCanvasTriggerSuite:v2';
  var menu = null;
  var testBar = null;
  var testAvatar = null;
  var testMode = false;
  var localHistory = new Map();

  var templates = [
    { id:'welcome', title:'Welcome Zone', label:'Message', type:'message', target:'new_customers', schedule:'business_hours', action:'message_only', message:'Welcome in, {first_name}. I can help with {campaign_title}.' },
    { id:'reward', title:'Reward Zone', label:'Reward', type:'reward', target:'everyone', schedule:'always', action:'message_and_reward', message:'Hi {first_name}, you entered the reward zone. Here is {campaign_title}.' },
    { id:'newsletter', title:'Newsletter Signup Zone', label:'Signup', type:'signup', target:'new_customers', schedule:'always', action:'crm_segment', message:'Join our local updates list and get access to {campaign_title}.' },
    { id:'vip', title:'VIP Customer Zone', label:'VIP', type:'vip', target:'returning_customers', schedule:'business_hours', action:'follow_up', message:'Thanks for coming back, {first_name}. I can help with {campaign_title}.' },
    { id:'interest', title:'Abandoned Interest Zone', label:'Follow-up', type:'followup', target:'unclaimed_rewards', schedule:'always', action:'follow_up', message:'Still interested? I can help you claim {campaign_title}.' },
    { id:'exit', title:'Exit Intent Zone', label:'Alert', type:'alert', target:'everyone', schedule:'always', action:'message_and_reward', message:'Before you go, here is one more reason to claim {campaign_title}.' },
    { id:'staff', title:'Staff Alert Zone', label:'Staff', type:'alert', target:'vip_customers', schedule:'business_hours', action:'notify_only', message:'Staff alert: {first_name} entered {trigger_name}.' }
  ];

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
  function payload(response) { return response && response.data ? response.data : response; }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, Number(value || 0))); }
  function safeId(id) { return String(id || '').replace(/[^a-zA-Z0-9_-]/g, ''); }
  function triggerLayer() { return MG.ensureCanvasTriggerLayer ? MG.ensureCanvasTriggerLayer() : (map.querySelector('[data-canvas-triggers]') || customerLayer); }
  function nodes() { return Array.from(document.querySelectorAll('[data-canvas-persistent-zone]')); }
  function activeNode() { return document.querySelector('[data-canvas-persistent-zone].is-settings-open') || nodes()[0] || null; }
  function activeId() { var node = activeNode(); return node ? String(node.dataset.canvasTriggerZone || '') : ''; }
  function form() { return document.querySelector('.mg-canvas-trigger-settings-drawer.is-open [data-trigger-settings-form]'); }
  function getStore() { try { return JSON.parse(localStorage.getItem(storageKey) || '{}') || {}; } catch (e) { return {}; } }
  function setStore(store) { localStorage.setItem(storageKey, JSON.stringify(store || {})); }
  function defaultSettings() { return { template_id:'reward', visual_type:'reward', target_rule:'everyone', schedule_rule:'always', schedule_start:'09:00', schedule_end:'17:00', schedule_days:'mon,tue,wed,thu,fri,sat,sun', build_score:10 }; }
  function settings(id) { return Object.assign(defaultSettings(), getStore()[id] || {}); }
  function saveSettings(id, next) { var store = getStore(); store[id] = Object.assign(defaultSettings(), store[id] || {}, next || {}); setStore(store); return store[id]; }
  function template(id) { return templates.find(function (item) { return item.id === id; }) || templates[1]; }
  function canvasRect() { return map.getBoundingClientRect(); }
  function relRect(node) { var r = node.getBoundingClientRect(); var b = canvasRect(); return { x:r.left-b.left, y:r.top-b.top, width:r.width, height:r.height }; }
  function overlap(a, b, pad) { pad = pad || 0; return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y; }
  function fmt(value) { if (!value) return 'Never'; return String(value).replace('T',' ').slice(0,19); }

  function optionList(items, selected) {
    return items.map(function (item) { return '<option value="' + esc(item[0]) + '"' + (String(selected) === String(item[0]) ? ' selected' : '') + '>' + esc(item[1]) + '</option>'; }).join('');
  }

  function isScheduleActive(s) {
    if (!s || s.schedule_rule === 'always' || s.schedule_rule === 'campaign_window') return true;
    var date = new Date();
    var keys = ['sun','mon','tue','wed','thu','fri','sat'];
    var today = keys[date.getDay()];
    if (s.schedule_rule === 'weekdays' && (today === 'sat' || today === 'sun')) return false;
    if (s.schedule_rule === 'weekends' && today !== 'sat' && today !== 'sun') return false;
    if (s.schedule_rule === 'custom' && String(s.schedule_days || '').split(',').map(function (d) { return d.trim(); }).indexOf(today) === -1) return false;
    if (s.schedule_rule === 'business_hours' || s.schedule_rule === 'custom') {
      var time = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
      return time >= String(s.schedule_start || '09:00') && time <= String(s.schedule_end || '17:00');
    }
    return true;
  }

  function buildScore(id) {
    var node = document.querySelector('[data-canvas-trigger-zone="' + CSS.escape(id) + '"]');
    var s = settings(id);
    var score = 0;
    if (node && node.querySelector('[data-zone-name]') && node.querySelector('[data-zone-name]').textContent.trim()) score++;
    if (node && node.querySelector('[data-zone-campaign]') && node.querySelector('[data-zone-campaign]').textContent.trim() !== 'No campaign assigned') score++;
    if (s.template_id) score++;
    if (s.visual_type) score++;
    if (s.target_rule) score++;
    if (s.schedule_rule) score++;
    if (s.schedule_start && s.schedule_end) score++;
    if (node && parseFloat(node.style.width || 0) > 80) score++;
    if (node && parseFloat(node.style.height || 0) > 50) score++;
    score++;
    return Math.min(10, score);
  }

  function decorateNodes() {
    nodes().forEach(function (node) {
      var id = String(node.dataset.canvasTriggerZone || '');
      if (!id) return;
      var s = settings(id);
      node.hidden = false;
      node.style.visibility = 'visible';
      node.classList.add('is-suite-managed');
      ['message','reward','signup','vip','followup','alert','analytics'].forEach(function (type) { node.classList.remove('is-type-' + type); });
      node.classList.add('is-type-' + safeId(s.visual_type || 'reward'));
      node.classList.toggle('is-scheduled-off', !isScheduleActive(s));
      var state = node.querySelector('[data-zone-suite-state]');
      if (!state) {
        state = document.createElement('b');
        state.setAttribute('data-zone-suite-state', '');
        var main = node.querySelector('.mg-canvas-trigger-main');
        if (main) main.appendChild(state);
      }
      state.textContent = !isScheduleActive(s) ? 'scheduled off' : (node.classList.contains('is-cooldown') ? 'cooling down' : 'active');
    });
  }

  function placement() {
    var used = nodes().map(relRect);
    var box = canvasRect();
    var candidates = [];
    [8, 28, 48, 68].forEach(function (x) { [10, 28, 46, 64, 78].forEach(function (y) { candidates.push({x:x,y:y,width:17,height:10}); }); });
    for (var i = 0; i < candidates.length; i++) {
      var c = candidates[i];
      var px = { x:box.width*c.x/100, y:box.height*c.y/100, width:box.width*c.width/100, height:box.height*c.height/100 };
      if (!used.some(function (u) { return overlap(px, u, 24); })) return c;
    }
    return candidates[nodes().length % candidates.length] || {x:12,y:12,width:17,height:10};
  }

  function ensureMenu() {
    if (menu && menu.isConnected) return menu;
    menu = document.createElement('aside');
    menu.className = 'mg-canvas-trigger-template-menu';
    menu.setAttribute('aria-hidden', 'true');
    menu.innerHTML = '<header><strong>Create Trigger</strong><button type="button" data-template-close>x</button></header><div class="mg-trigger-template-grid">' + templates.map(function (t) { return '<button type="button" data-template-id="' + esc(t.id) + '"><strong>' + esc(t.title) + '</strong><small>' + esc(t.message) + '</small><b>' + esc(t.label) + '</b></button>'; }).join('') + '</div>';
    map.appendChild(menu);
    menu.addEventListener('click', function (event) {
      if (event.target.closest('[data-template-close]')) return closeMenu();
      var button = event.target.closest('[data-template-id]');
      if (!button) return;
      closeMenu();
      createTemplate(button.dataset.templateId);
    });
    return menu;
  }
  function openMenu() { var m = ensureMenu(); m.classList.add('is-open'); m.setAttribute('aria-hidden','false'); }
  function closeMenu() { if (!menu) return; menu.classList.remove('is-open'); menu.setAttribute('aria-hidden','true'); }

  function createTemplate(id) {
    var t = template(id);
    var p = placement();
    MG.post('/api/merchant-canvas/trigger-zone-save.php', { name:t.title, trigger_key:'store_canvas_' + t.id + '_' + Date.now(), campaign_id:'', priority:3, x:p.x, y:p.y, width:p.width, height:p.height, status:'active', automation_action:t.action, cooldown_policy:'fifteen_minutes', cooldown_seconds:900, auto_message_text:t.message, fallback_action:'notify_only', crm_segment_name:t.id === 'newsletter' ? 'Newsletter Signup' : '', notify_merchant:1 }).then(function (response) {
      var data = payload(response) || {};
      if (data.zone && data.zone.id) saveSettings(String(data.zone.id), { template_id:t.id, visual_type:t.type, target_rule:t.target, schedule_rule:t.schedule, build_score:10 });
      toast('Trigger template created.', 'success');
      window.setTimeout(function () { window.location.reload(); }, 350);
    }).catch(function (error) { toast(error.message || 'Unable to create trigger.', 'error'); });
  }

  function ensureTestBar() {
    if (testBar && testBar.isConnected) return testBar;
    testBar = document.createElement('div');
    testBar.className = 'mg-canvas-test-mode-bar';
    testBar.innerHTML = '<button type="button" data-test-toggle>Test Mode: Off</button><button type="button" data-test-run disabled>Run Test</button>';
    map.appendChild(testBar);
    testBar.addEventListener('click', function (event) {
      if (event.target.closest('[data-test-toggle]')) toggleTestMode();
      if (event.target.closest('[data-test-run]')) runTest();
    });
    return testBar;
  }
  function toggleTestMode() {
    testMode = !testMode;
    root.classList.toggle('is-trigger-test-mode', testMode);
    ensureTestBar().querySelector('[data-test-toggle]').textContent = testMode ? 'Test Mode: On' : 'Test Mode: Off';
    ensureTestBar().querySelector('[data-test-run]').disabled = !testMode;
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
    triggerLayer().appendChild(testAvatar);
    return testAvatar;
  }
  function runTest() {
    var node = activeNode();
    if (!node) return toast('Create or select a trigger first.', 'info');
    var avatar = ensureTestAvatar();
    var r = relRect(node);
    avatar.style.left = Math.round(r.x + r.width / 2 - 56) + 'px';
    avatar.style.top = Math.round(r.y + r.height / 2 - 20) + 'px';
    addHistory(String(node.dataset.canvasTriggerZone || ''), { created_at:new Date().toISOString(), customer_name:'Test Customer', campaign_title:node.querySelector('[data-zone-campaign]') ? node.querySelector('[data-zone-campaign]').textContent : 'Test campaign' });
    node.classList.add('is-hot');
    window.setTimeout(function () { node.classList.remove('is-hot'); }, 2200);
    toast('Test trigger passed. No live message or reward was sent.', 'success');
    renderHistory(String(node.dataset.canvasTriggerZone || ''));
  }

  function addHistory(id, item) {
    if (!id) return;
    if (!localHistory.has(id)) localHistory.set(id, []);
    localHistory.get(id).unshift(item);
    localHistory.set(id, localHistory.get(id).slice(0, 10));
  }

  function drawerHtml(s, id) {
    return '<div class="mg-trigger-score-card"><strong>Build Score ' + buildScore(id) + '/10</strong><span>' + (buildScore(id) >= 10 ? '10/10 production-ready trigger.' : 'Complete campaign, template, targeting, schedule, and size.') + '</span></div>' +
      '<div class="mg-trigger-settings-row"><label>Template<select name="suite_template_id">' + templates.map(function (t) { return '<option value="' + esc(t.id) + '"' + (s.template_id === t.id ? ' selected' : '') + '>' + esc(t.title) + '</option>'; }).join('') + '</select></label><label>Visual type<select name="suite_visual_type">' + optionList([['message','Message zone'],['reward','Reward zone'],['signup','Signup zone'],['vip','VIP zone'],['followup','Follow-up zone'],['alert','Alert zone'],['analytics','Analytics only']], s.visual_type) + '</select></label></div>' +
      '<div class="mg-trigger-settings-row"><label>Targeting<select name="suite_target_rule">' + optionList([['everyone','Everyone'],['new_customers','New customers'],['returning_customers','Returning customers'],['unclaimed_rewards','Customers with unclaimed rewards'],['campaign_customers','Selected campaign customers'],['vip_customers','VIP / high intent customers']], s.target_rule) + '</select></label><label>Schedule<select name="suite_schedule_rule">' + optionList([['always','Always active'],['business_hours','Business hours'],['weekdays','Weekdays only'],['weekends','Weekends only'],['campaign_window','Campaign window'],['custom','Custom days + hours']], s.schedule_rule) + '</select></label></div>' +
      '<div class="mg-trigger-settings-row"><label>Start<input type="time" name="suite_schedule_start" value="' + esc(s.schedule_start) + '"></label><label>End<input type="time" name="suite_schedule_end" value="' + esc(s.schedule_end) + '"></label></div>' +
      '<label>Days<input name="suite_schedule_days" value="' + esc(s.schedule_days) + '"><small>mon,tue,wed,thu,fri,sat,sun</small></label>' +
      '<section class="mg-trigger-drawer-history" data-suite-history><strong>Trigger history</strong><p>No local test events yet.</p></section>';
  }

  function augmentDrawer() {
    var f = form();
    var id = activeId();
    if (!f || !id) return;
    var panel = f.querySelector('[data-suite-panel]');
    if (!panel) {
      panel = document.createElement('section');
      panel.className = 'mg-trigger-suite-panel';
      panel.setAttribute('data-suite-panel', '');
      f.insertBefore(panel, f.firstChild);
      f.addEventListener('change', onPanelChange, true);
      f.addEventListener('input', onPanelChange, true);
      f.addEventListener('click', onPanelClick, true);
    }
    panel.innerHTML = drawerHtml(settings(id), id);
    var actions = f.querySelector('.mg-trigger-settings-actions');
    if (actions && !actions.querySelector('[data-suite-duplicate]')) actions.insertAdjacentHTML('afterbegin', '<button type="button" data-suite-duplicate>Duplicate Trigger</button><button type="button" data-suite-run-test>Run Test</button>');
    renderHistory(id);
  }

  function onPanelChange(event) {
    if (!event.target.name || event.target.name.indexOf('suite_') !== 0) return;
    var id = activeId();
    var f = form();
    if (!id || !f) return;
    var s = settings(id);
    s.template_id = f.elements.suite_template_id ? f.elements.suite_template_id.value : s.template_id;
    s.visual_type = f.elements.suite_visual_type ? f.elements.suite_visual_type.value : s.visual_type;
    s.target_rule = f.elements.suite_target_rule ? f.elements.suite_target_rule.value : s.target_rule;
    s.schedule_rule = f.elements.suite_schedule_rule ? f.elements.suite_schedule_rule.value : s.schedule_rule;
    s.schedule_start = f.elements.suite_schedule_start ? f.elements.suite_schedule_start.value : s.schedule_start;
    s.schedule_end = f.elements.suite_schedule_end ? f.elements.suite_schedule_end.value : s.schedule_end;
    s.schedule_days = f.elements.suite_schedule_days ? f.elements.suite_schedule_days.value : s.schedule_days;
    if (event.target.name === 'suite_template_id') {
      var t = template(s.template_id);
      s.visual_type = t.type; s.target_rule = t.target; s.schedule_rule = t.schedule;
      if (f.elements.name) f.elements.name.value = t.title;
      if (f.elements.automation_action) f.elements.automation_action.value = t.action;
      if (f.elements.auto_message_text) f.elements.auto_message_text.value = t.message;
    }
    s.build_score = 10;
    saveSettings(id, s);
    decorateNodes();
  }

  function onPanelClick(event) {
    if (event.target.closest('[data-suite-run-test]')) { event.preventDefault(); if (!testMode) toggleTestMode(); runTest(); }
    if (event.target.closest('[data-suite-duplicate]')) { event.preventDefault(); duplicateTrigger(); }
  }

  function duplicateTrigger() {
    var f = form();
    var node = activeNode();
    var id = activeId();
    if (!f || !node || !id) return;
    var box = canvasRect();
    var r = relRect(node);
    MG.post('/api/merchant-canvas/trigger-zone-save.php', { name:(f.elements.name ? f.elements.name.value : 'Trigger Zone') + ' Copy', trigger_key:'store_canvas_copy_' + Date.now(), campaign_id:f.elements.campaign_id ? f.elements.campaign_id.value : '', priority:f.elements.priority ? f.elements.priority.value : 3, x:clamp(((r.x + 36) / box.width) * 100, 0, 88), y:clamp(((r.y + 36) / box.height) * 100, 0, 88), width:clamp((r.width / box.width) * 100, 4, 100), height:clamp((r.height / box.height) * 100, 4, 100), status:'active', automation_action:f.elements.automation_action ? f.elements.automation_action.value : 'message_and_reward', cooldown_policy:f.elements.cooldown_policy ? f.elements.cooldown_policy.value : 'fifteen_minutes', cooldown_seconds:f.elements.cooldown_seconds ? f.elements.cooldown_seconds.value : 900, auto_message_text:f.elements.auto_message_text ? f.elements.auto_message_text.value : '', fallback_action:f.elements.fallback_action ? f.elements.fallback_action.value : 'notify_only', crm_segment_name:f.elements.crm_segment_name ? f.elements.crm_segment_name.value : '', notify_merchant:1 }).then(function (response) {
      var data = payload(response) || {};
      if (data.zone && data.zone.id) saveSettings(String(data.zone.id), settings(id));
      toast('Trigger duplicated.', 'success');
      window.setTimeout(function () { window.location.reload(); }, 350);
    }).catch(function (error) { toast(error.message || 'Unable to duplicate trigger.', 'error'); });
  }

  function renderHistory(id) {
    var panel = document.querySelector('[data-suite-history]');
    if (!panel || !id) return;
    var local = localHistory.get(id) || [];
    panel.innerHTML = '<strong>Trigger history</strong><div class="mg-trigger-history-stats"><article><span>Score</span><strong>' + buildScore(id) + '/10</strong></article><article><span>Tests</span><strong>' + local.length + '</strong></article><article><span>State</span><strong>' + esc(stateText(id)) + '</strong></article></div>' + (local.length ? local.map(function (item) { return '<div class="mg-trigger-history-row"><strong>' + esc(item.customer_name || 'Customer') + '</strong><span>' + esc(fmt(item.created_at)) + '</span><small>' + esc(item.campaign_title || 'Trigger event') + '</small><em><b>test</b></em></div>'; }).join('') : '<p>No local test events yet. Use Test Mode or Run Test.</p>');
    if (!MG.get) return;
    MG.get('/api/merchant-canvas/trigger-zone-analytics.php?zone_id=' + encodeURIComponent(id)).then(function (response) {
      var data = payload(response) || {};
      var stats = data.stats || {};
      var events = Array.isArray(data.events) ? data.events : [];
      var rows = events.slice(0, 5).map(function (event) { return '<div class="mg-trigger-history-row"><strong>' + esc(event.customer_name || 'Customer') + '</strong><span>' + esc(fmt(event.created_at)) + '</span><small>' + esc(event.campaign_title || event.event_label || 'Trigger event') + '</small><em>' + (event.reward_sent ? '<b>reward</b>' : '<b>event</b>') + '</em></div>'; }).join('');
      panel.innerHTML = '<strong>Trigger history</strong><div class="mg-trigger-history-stats"><article><span>Score</span><strong>' + buildScore(id) + '/10</strong></article><article><span>Fires</span><strong>' + esc(stats.fires || 0) + '</strong></article><article><span>Messages</span><strong>' + esc(stats.messages_sent || 0) + '</strong></article><article><span>Rewards</span><strong>' + esc(stats.rewards_sent || 0) + '</strong></article></div>' + (rows || '<p>No live trigger events yet.</p>');
    }).catch(function () {});
  }

  function stateText(id) {
    var node = document.querySelector('[data-canvas-trigger-zone="' + CSS.escape(id) + '"]');
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
      if (String(url || '').indexOf('/api/merchant-canvas/campaign-trigger') !== -1 && data && data.trigger_zone_id && !isScheduleActive(settings(String(data.trigger_zone_id)))) {
        return Promise.resolve({ data:{ skipped:true, schedule_off:true }, message:'Trigger schedule inactive.' });
      }
      return original.apply(this, arguments);
    };
    MG.__triggerSuitePostPatched = true;
  }

  function interceptCreateButton() {
    map.addEventListener('click', function (event) {
      var button = event.target.closest('[data-persistent-trigger-button]');
      if (!button) return;
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) event.stopImmediatePropagation();
      openMenu();
    }, true);
  }

  function observe() {
    var observer = new MutationObserver(function () { decorateNodes(); augmentDrawer(); });
    observer.observe(document.body, { childList:true, subtree:true, attributes:true, attributeFilter:['class','style','data-canvas-trigger-zone'] });
    window.setInterval(function () { decorateNodes(); augmentDrawer(); }, 900);
  }

  function init() {
    ensureMenu(); ensureTestBar(); interceptCreateButton(); patchPostForSchedule(); observe(); decorateNodes();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once:true }); else init();
})(window, document);
