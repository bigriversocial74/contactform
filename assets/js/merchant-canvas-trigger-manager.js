window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.post) return;

  var map = root.querySelector('[data-canvas-map]');
  var layer = root.querySelector('[data-canvas-customers]');
  if (!map || !layer) return;

  var campaigns = [];
  var campaignLoaded = false;
  var zones = [];
  var els = new Map();
  var lastFire = new Map();
  var drag = null;
  var button = null;
  var analyticsDrawer = null;
  var activeAnalyticsZone = '';
  var analyticsTimer = null;
  var settingsDrawer = null;
  var activeSettingsZone = '';
  var settingsSaveTimer = null;
  var lastDragAt = 0;

  var defaultMessage = 'Hi {first_name} — you entered the {trigger_name} zone. I sent this to your IN/OUT Box so you can review the offer or ask questions.';

  function payload(r) { return r && r.data ? r.data : r; }
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function now() { return Date.now(); }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function toast(m, t) { if (MG.toast) MG.toast(m, t || 'info'); }
  function tempId() { return 'tmp-' + Date.now() + '-' + Math.floor(Math.random() * 10000); }
  function pri(v) { return clamp(parseInt(v || 3, 10) || 3, 1, 5); }
  function isTmp(z) { return z && String(z.id || '').indexOf('tmp-') === 0; }
  function fmt(value) { var raw = String(value || '').trim(); return raw ? raw.replace('T', ' ').slice(0, 19) : '—'; }
  function boolInt(value) { return value === true || value === 1 || value === '1' || value === 'true' || value === 'on' ? 1 : 0; }

  function clearLegacy() {
    try { window.localStorage.removeItem('mgCanvasTriggerConfig:v2'); } catch (e) {}
    Array.from(map.querySelectorAll('.mg-canvas-trigger-add-btn:not([data-persistent-trigger-button])')).forEach(function (el) { el.remove(); });
    Array.from(layer.querySelectorAll('.mg-canvas-trigger-zone:not([data-canvas-persistent-zone])')).forEach(function (el) { el.hidden = true; el.remove(); });
  }

  function zoneDefaults() {
    var offset = zones.length % 5;
    return {
      id: tempId(),
      name: 'IN/OUT Box Trigger ' + (zones.length + 1),
      trigger_key: 'store_canvas_zone',
      campaign_id: campaigns[0] ? String(campaigns[0].id || '') : '',
      campaign_title: campaigns[0] ? String(campaigns[0].title || '') : '',
      priority: 3,
      x: clamp(68 - offset * 5, 2, 72),
      y: clamp(66 - offset * 5, 2, 72),
      width: 28,
      height: 18,
      status: 'active',
      automation_action: 'message_and_reward',
      cooldown_policy: 'fifteen_minutes',
      cooldown_seconds: 900,
      auto_message_text: defaultMessage,
      fallback_action: 'notify_only',
      crm_segment_name: '',
      notify_merchant: 1,
      saving: true
    };
  }

  function campaignOptions(current) {
    if (!campaignLoaded) return '<option value="">Loading campaigns...</option>';
    if (!campaigns.length) return '<option value="">No active campaigns</option>';
    return campaigns.map(function (c) {
      var id = String(c.id || '');
      var label = String(c.title || 'Campaign') + (c.reward_template_title ? ' · ' + String(c.reward_template_title) : '');
      return '<option value="' + esc(id) + '"' + (id === String(current || '') ? ' selected' : '') + '>' + esc(label) + '</option>';
    }).join('');
  }

  function campaignTitle(id) {
    var found = campaigns.find(function (c) { return String(c.id || '') === String(id || ''); });
    return found ? String(found.title || 'Campaign') : '';
  }

  function priorityOptions(current) {
    var currentPriority = pri(current);
    var html = '';
    for (var i = 1; i <= 5; i++) html += '<option value="' + i + '"' + (i === currentPriority ? ' selected' : '') + '>' + i + '</option>';
    return html;
  }

  function optionList(items, current) {
    return items.map(function (item) {
      return '<option value="' + esc(item[0]) + '"' + (String(current || '') === String(item[0]) ? ' selected' : '') + '>' + esc(item[1]) + '</option>';
    }).join('');
  }

  function ensureButton() {
    clearLegacy();
    if (button && button.isConnected) return button;
    button = document.createElement('button');
    button.type = 'button';
    button.className = 'mg-canvas-trigger-add-btn';
    button.setAttribute('data-persistent-trigger-button', '1');
    button.innerHTML = '<span>+</span> Trigger';
    map.appendChild(button);
    button.addEventListener('click', function () {
      var z = zoneDefaults();
      zones.unshift(z);
      render();
      openSettings(z);
      saveZone(z, true);
    });
    return button;
  }

  function pxRect(z) {
    var b = layer.getBoundingClientRect();
    var width = clamp(b.width * (Number(z.width || 28) / 100), 160, Math.max(160, b.width - 16));
    var height = clamp(b.height * (Number(z.height || 18) / 100), 112, Math.max(112, b.height - 16));
    var x = clamp(b.width * (Number(z.x || 0) / 100), 8, Math.max(8, b.width - width - 8));
    var y = clamp(b.height * (Number(z.y || 0) / 100), 8, Math.max(8, b.height - height - 8));
    return { x: x, y: y, width: width, height: height };
  }

  function setFromPx(z, r) {
    var b = layer.getBoundingClientRect();
    z.width = clamp((r.width / b.width) * 100, 1, 100);
    z.height = clamp((r.height / b.height) * 100, 1, 100);
    z.x = clamp((r.x / b.width) * 100, 0, Math.max(0, 100 - z.width));
    z.y = clamp((r.y / b.height) * 100, 0, Math.max(0, 100 - z.height));
  }

  function zoneById(id) { return zones.find(function (z) { return String(z.id || '') === String(id || ''); }) || null; }

  function ensureZoneEl(z) {
    var id = String(z.id || '');
    var el = els.get(id);
    if (el && el.isConnected) return el;
    el = document.createElement('div');
    el.className = 'mg-canvas-trigger-zone';
    el.setAttribute('data-canvas-persistent-zone', '1');
    el.setAttribute('data-canvas-trigger-zone', id);
    el.innerHTML =
      '<span class="mg-canvas-trigger-zone-icon">IO</span>' +
      '<span class="mg-canvas-trigger-zone-copy">' +
        '<span class="mg-canvas-trigger-zone-title"><strong data-trigger-inline-name>IN/OUT Box Trigger</strong><b data-trigger-inline-state>Active</b></span>' +
        '<span data-trigger-inline-action>Message + reward trigger</span>' +
        '<span class="mg-canvas-trigger-row">' +
          '<label>Campaign<select data-trigger-campaign></select></label>' +
          '<label class="mg-canvas-trigger-priority">Priority<select data-trigger-priority></select></label>' +
        '</span>' +
        '<em data-trigger-status>Drag or resize anywhere on the canvas.</em>' +
      '</span>' +
      '<span class="mg-canvas-trigger-actions">' +
        '<button type="button" data-trigger-settings title="Open trigger settings">⚙</button>' +
        '<button type="button" data-trigger-analytics title="View analytics">↗</button>' +
        '<button type="button" data-trigger-toggle title="Enable or pause trigger">●</button>' +
        '<button type="button" data-trigger-delete title="Delete trigger">×</button>' +
      '</span>' +
      '<span class="mg-canvas-trigger-drag-hint">Click for settings · drag zone</span>' +
      '<span class="mg-canvas-trigger-resize" data-trigger-resize aria-hidden="true"></span>';
    layer.appendChild(el);
    els.set(id, el);
    return el;
  }

  function actionLabel(value) {
    return ({ message_and_reward:'Message + reward', message_only:'Message only', reward_only:'Reward only', notify_only:'Notify merchant', follow_up:'Follow-up task', crm_segment:'CRM segment', analytics_only:'Analytics only' })[String(value || 'message_and_reward')] || 'Message + reward';
  }
  function cooldownLabel(value) {
    return ({ five_minutes:'5 min cooldown', fifteen_minutes:'15 min cooldown', one_hour:'1 hr cooldown', once_per_visit:'Once per visit', once_per_customer_day:'Once per day' })[String(value || 'fifteen_minutes')] || '15 min cooldown';
  }

  function updateEl(z, el) {
    el.dataset.canvasTriggerZone = String(z.id || '');
    el.classList.toggle('is-paused', z.status === 'paused');
    el.classList.toggle('is-saving', !!z.saving);
    el.classList.toggle('is-settings-open', String(z.id || '') === activeSettingsZone);
    var r = pxRect(z);
    el.style.left = Math.round(r.x) + 'px';
    el.style.top = Math.round(r.y) + 'px';
    el.style.width = Math.round(r.width) + 'px';
    el.style.height = Math.round(r.height) + 'px';
    var name = el.querySelector('[data-trigger-inline-name]');
    var inlineState = el.querySelector('[data-trigger-inline-state]');
    var inlineAction = el.querySelector('[data-trigger-inline-action]');
    var campaign = el.querySelector('[data-trigger-campaign]');
    var priority = el.querySelector('[data-trigger-priority]');
    var status = el.querySelector('[data-trigger-status]');
    var toggle = el.querySelector('[data-trigger-toggle]');
    if (name) name.textContent = String(z.name || 'IN/OUT Box Trigger');
    if (inlineState) inlineState.textContent = z.status === 'paused' ? 'Paused' : 'Active';
    if (inlineAction) inlineAction.textContent = actionLabel(z.automation_action) + (z.cooldown_policy ? ' · ' + cooldownLabel(z.cooldown_policy) : '');
    if (campaign) { campaign.disabled = !campaignLoaded || !campaigns.length; campaign.innerHTML = campaignOptions(z.campaign_id); }
    if (priority) priority.innerHTML = priorityOptions(z.priority);
    if (toggle) toggle.textContent = z.status === 'paused' ? '○' : '●';
    if (status) status.textContent = z.saving ? 'Saving trigger settings...' : (z.status === 'paused' ? 'Paused. Customer avatars will not fire this trigger.' : 'Priority ' + pri(z.priority) + '. Click to open settings.');
  }

  function render() {
    ensureButton();
    var active = new Set(zones.map(function (z) { return String(z.id || ''); }));
    els.forEach(function (el, id) { if (!active.has(id)) { el.remove(); els.delete(id); } });
    zones.forEach(function (z) { updateEl(z, ensureZoneEl(z)); });
    if (activeSettingsZone) refreshSettingsDrawer();
  }

  function replaceZones(list) {
    zones = Array.isArray(list) ? list.map(function (z) {
      return { id:String(z.id || tempId()), name:String(z.name || 'IN/OUT Box Trigger'), trigger_key:String(z.trigger_key || 'store_canvas_zone'), campaign_id:String(z.campaign_id || ''), campaign_title:String(z.campaign_title || ''), priority:pri(z.priority), x:Number(z.x || 0), y:Number(z.y || 0), width:Number(z.width || 28), height:Number(z.height || 18), status:String(z.status || 'active'), automation_action:String(z.automation_action || 'message_and_reward'), cooldown_policy:String(z.cooldown_policy || 'fifteen_minutes'), cooldown_seconds:Number(z.cooldown_seconds || 900), auto_message_text:String(z.auto_message_text || defaultMessage), fallback_action:String(z.fallback_action || 'notify_only'), crm_segment_name:String(z.crm_segment_name || ''), notify_merchant:boolInt(z.notify_merchant == null ? 1 : z.notify_merchant), saving:false };
    }) : [];
    render();
  }

  async function loadCampaigns() {
    if (!MG.get) { campaignLoaded = true; render(); return; }
    try {
      var data = payload(await MG.get('/api/merchant-canvas/reward-options.php')) || {};
      campaigns = Array.isArray(data.campaigns) ? data.campaigns.filter(function (c) { return c && c.available !== false; }) : [];
    } catch (e) { campaigns = []; }
    campaignLoaded = true;
    render();
  }

  async function loadZones() {
    if (!MG.get) { render(); return; }
    try { var data = payload(await MG.get('/api/merchant-canvas/trigger-zones.php')) || {}; replaceZones(data.zones || []); }
    catch (e) { replaceZones([]); }
  }

  function zonePayload(z) {
    return { id:isTmp(z) ? '' : z.id, name:z.name || 'IN/OUT Box Trigger', trigger_key:z.trigger_key || 'store_canvas_zone', campaign_id:z.campaign_id || '', priority:pri(z.priority), x:Number(z.x || 0), y:Number(z.y || 0), width:Number(z.width || 28), height:Number(z.height || 18), status:z.status || 'active', automation_action:z.automation_action || 'message_and_reward', cooldown_policy:z.cooldown_policy || 'fifteen_minutes', cooldown_seconds:Math.max(60, parseInt(z.cooldown_seconds || 900, 10) || 900), auto_message_text:z.auto_message_text || '', fallback_action:z.fallback_action || 'notify_only', crm_segment_name:z.crm_segment_name || '', notify_merchant:boolInt(z.notify_merchant == null ? 1 : z.notify_merchant) };
  }

  function setSettingsStatus(message, state) {
    if (!settingsDrawer) return;
    var status = settingsDrawer.querySelector('[data-trigger-settings-status]');
    if (!status) return;
    status.textContent = message || '';
    status.className = 'mg-trigger-settings-status' + (state ? ' is-' + state : '');
  }

  async function saveZone(z, created) {
    if (!z) return;
    z.saving = true;
    render();
    setSettingsStatus('Saving trigger settings...', 'saving');
    try {
      var oldId = String(z.id || '');
      var data = payload(await MG.post('/api/merchant-canvas/trigger-zone-save.php', zonePayload(z))) || {};
      if (Array.isArray(data.zones)) replaceZones(data.zones);
      else if (data.zone) {
        if (isTmp(z)) { var oldEl = els.get(oldId); if (oldEl) oldEl.remove(); els.delete(oldId); activeSettingsZone = String(data.zone.id || ''); }
        replaceZones(zones.map(function (item) { return String(item.id) === oldId ? data.zone : item; }));
      }
      setSettingsStatus('Saved.', 'success');
      if (created) toast('Trigger saved. Finish the trigger settings in the slide-out.', 'success');
    } catch (e) { z.saving = false; render(); setSettingsStatus(e.message || 'Unable to save trigger settings.', 'error'); toast(e.message || 'Unable to save trigger zone.', 'error'); }
  }

  function scheduleSettingsSave(z) {
    if (!z) return;
    if (settingsSaveTimer) window.clearTimeout(settingsSaveTimer);
    setSettingsStatus('Unsaved changes...', 'saving');
    settingsSaveTimer = window.setTimeout(function () { saveZone(z, false); }, 750);
  }

  async function removeZone(z) {
    if (!z || !window.confirm('Delete this trigger zone?')) return;
    closeSettings();
    if (isTmp(z)) { zones = zones.filter(function (item) { return item !== z; }); render(); return; }
    try { var data = payload(await MG.post('/api/merchant-canvas/trigger-zone-delete.php', { id: z.id })) || {}; if (Array.isArray(data.zones)) replaceZones(data.zones); else { zones = zones.filter(function (item) { return String(item.id) !== String(z.id); }); render(); } toast('Trigger zone deleted.', 'success'); }
    catch (e) { toast(e.message || 'Unable to delete trigger zone.', 'error'); }
  }

  function ensureSettingsDrawer() {
    if (settingsDrawer && settingsDrawer.isConnected) return settingsDrawer;
    settingsDrawer = document.createElement('aside');
    settingsDrawer.className = 'mg-canvas-trigger-settings-drawer';
    settingsDrawer.setAttribute('aria-hidden', 'true');
    settingsDrawer.innerHTML = '<div class="mg-trigger-settings-head"><div><span>Trigger Settings</span><h2 data-trigger-settings-title>Select a trigger</h2></div><button type="button" data-trigger-settings-close aria-label="Close trigger settings">×</button></div><div class="mg-trigger-settings-body" data-trigger-settings-body><p>Click a trigger on the Store Canvas to edit its behavior.</p></div><div class="mg-trigger-settings-foot"><p class="mg-trigger-settings-status" data-trigger-settings-status role="status"></p><button type="button" class="mg-btn mg-btn-primary" data-trigger-settings-save>Save Settings</button></div>';
    document.body.appendChild(settingsDrawer);
    settingsDrawer.addEventListener('click', function (event) {
      if (event.target.closest('[data-trigger-settings-close]')) return closeSettings();
      if (event.target.closest('[data-trigger-settings-save]')) { var zone = zoneById(activeSettingsZone); if (zone) saveZone(zone, false); }
      if (event.target.closest('[data-trigger-settings-analytics]')) { var az = zoneById(activeSettingsZone); if (az) loadAnalytics(String(az.id), true); }
      if (event.target.closest('[data-trigger-settings-delete]')) removeZone(zoneById(activeSettingsZone));
    });
    settingsDrawer.addEventListener('change', handleSettingsChange);
    settingsDrawer.addEventListener('input', handleSettingsInput);
    return settingsDrawer;
  }

  function closeSettings() {
    if (!settingsDrawer) return;
    settingsDrawer.classList.remove('is-open');
    settingsDrawer.setAttribute('aria-hidden', 'true');
    activeSettingsZone = '';
    if (settingsSaveTimer) window.clearTimeout(settingsSaveTimer);
    settingsSaveTimer = null;
    render();
  }
  function openSettings(z) { if (!z) return; closeAnalytics(); activeSettingsZone = String(z.id || ''); var drawer = ensureSettingsDrawer(); drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false'); renderSettings(z); render(); }
  function refreshSettingsDrawer() { if (!settingsDrawer || !settingsDrawer.classList.contains('is-open')) return; var z = zoneById(activeSettingsZone); if (z) renderSettings(z); }

  function renderSettings(z) {
    var drawer = ensureSettingsDrawer();
    var title = drawer.querySelector('[data-trigger-settings-title]');
    var body = drawer.querySelector('[data-trigger-settings-body]');
    if (title) title.textContent = z.name || 'Trigger settings';
    if (!body) return;
    body.innerHTML = '<form class="mg-trigger-settings-form" data-trigger-settings-form>' +
      '<section class="mg-trigger-settings-summary"><article><span>Canvas trigger</span><strong>' + esc(z.status === 'paused' ? 'Paused' : 'Active') + '</strong></article><article><span>Priority</span><strong>' + esc(pri(z.priority)) + '</strong></article><article><span>Last fired</span><strong>' + esc(fmt(z.last_triggered_at)) + '</strong></article></section>' +
      '<label>Trigger name<input name="name" maxlength="160" value="' + esc(z.name || 'IN/OUT Box Trigger') + '"></label>' +
      '<label>Trigger key<input name="trigger_key" maxlength="120" value="' + esc(z.trigger_key || 'store_canvas_zone') + '"></label>' +
      '<div class="mg-trigger-settings-row"><label>Assigned campaign<select name="campaign_id"' + (!campaignLoaded || !campaigns.length ? ' disabled' : '') + '>' + campaignOptions(z.campaign_id) + '</select></label><label>Priority<select name="priority">' + priorityOptions(z.priority) + '</select></label></div>' +
      '<div class="mg-trigger-settings-row"><label>Status<select name="status">' + optionList([['active','Active'],['paused','Paused']], z.status || 'active') + '</select></label><label>Automation action<select name="automation_action">' + optionList([['message_and_reward','Send message + reward'],['message_only','Send message only'],['reward_only','Send reward only'],['notify_only','Notify merchant only'],['follow_up','Create follow-up context'],['crm_segment','Add CRM segment'],['analytics_only','Analytics only']], z.automation_action || 'message_and_reward') + '</select></label></div>' +
      '<div class="mg-trigger-settings-row"><label>Cooldown policy<select name="cooldown_policy">' + optionList([['five_minutes','Five minutes'],['fifteen_minutes','Fifteen minutes'],['one_hour','One hour'],['once_per_visit','Once per visit'],['once_per_customer_day','Once per customer per day']], z.cooldown_policy || 'fifteen_minutes') + '</select></label><label>Cooldown seconds<input name="cooldown_seconds" type="number" min="60" max="86400" step="60" value="' + esc(Math.max(60, parseInt(z.cooldown_seconds || 900, 10) || 900)) + '"></label></div>' +
      '<label>Auto message<textarea name="auto_message_text" rows="5" maxlength="1000" placeholder="' + esc(defaultMessage) + '">' + esc(z.auto_message_text || '') + '</textarea><small>Tokens: {first_name}, {trigger_name}, {campaign_title}</small></label>' +
      '<div class="mg-trigger-settings-row"><label>Stamp fallback<select name="fallback_action">' + optionList([['notify_only','Notify merchant'],['analytics_only','Record analytics only'],['skip','Skip trigger']], z.fallback_action || 'notify_only') + '</select></label><label>CRM segment<input name="crm_segment_name" maxlength="160" value="' + esc(z.crm_segment_name || '') + '" placeholder="VIP, Local regular, Contest lead"></label></div>' +
      '<label class="mg-trigger-settings-check"><input type="checkbox" name="notify_merchant" value="1"' + (boolInt(z.notify_merchant == null ? 1 : z.notify_merchant) ? ' checked' : '') + '> Notify merchant when this trigger fires</label>' +
      '<section class="mg-trigger-settings-actions"><button type="button" data-trigger-settings-analytics>Open analytics</button><button type="button" data-trigger-settings-delete>Delete trigger</button></section></form>';
  }

  function readSettingsForm(form, z) {
    if (!form || !z) return;
    z.name = (form.elements.name ? form.elements.name.value.trim() : z.name) || 'IN/OUT Box Trigger';
    z.trigger_key = (form.elements.trigger_key ? form.elements.trigger_key.value.trim() : z.trigger_key) || 'store_canvas_zone';
    z.campaign_id = form.elements.campaign_id ? form.elements.campaign_id.value || '' : z.campaign_id || '';
    z.campaign_title = campaignTitle(z.campaign_id);
    z.priority = form.elements.priority ? pri(form.elements.priority.value) : pri(z.priority);
    z.status = form.elements.status ? form.elements.status.value || 'active' : z.status || 'active';
    z.automation_action = form.elements.automation_action ? form.elements.automation_action.value || 'message_and_reward' : z.automation_action || 'message_and_reward';
    z.cooldown_policy = form.elements.cooldown_policy ? form.elements.cooldown_policy.value || 'fifteen_minutes' : z.cooldown_policy || 'fifteen_minutes';
    z.cooldown_seconds = form.elements.cooldown_seconds ? Math.max(60, parseInt(form.elements.cooldown_seconds.value || 900, 10) || 900) : Math.max(60, parseInt(z.cooldown_seconds || 900, 10) || 900);
    z.auto_message_text = form.elements.auto_message_text ? form.elements.auto_message_text.value.trim() : z.auto_message_text || '';
    z.fallback_action = form.elements.fallback_action ? form.elements.fallback_action.value || 'notify_only' : z.fallback_action || 'notify_only';
    z.crm_segment_name = form.elements.crm_segment_name ? form.elements.crm_segment_name.value.trim() : z.crm_segment_name || '';
    z.notify_merchant = form.elements.notify_merchant && form.elements.notify_merchant.checked ? 1 : 0;
  }
  function handleSettingsChange(event) { var form = event.target.closest('[data-trigger-settings-form]'); var z = zoneById(activeSettingsZone); if (!form || !z) return; readSettingsForm(form, z); render(); scheduleSettingsSave(z); }
  function handleSettingsInput(event) { if (!event.target.matches('input,textarea')) return; var form = event.target.closest('[data-trigger-settings-form]'); var z = zoneById(activeSettingsZone); if (!form || !z) return; readSettingsForm(form, z); render(); scheduleSettingsSave(z); }

  function overlaps(a, b, pad) { pad = pad || 0; return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y; }
  function relRect(el) { var r = el.getBoundingClientRect(); var b = layer.getBoundingClientRect(); return { x: r.left - b.left, y: r.top - b.top, width: r.width, height: r.height }; }
  function zoneRect(z) { if (!z || z.status === 'paused' || isTmp(z) || z.saving) return null; var el = els.get(String(z.id)); if (!el) return null; var r = pxRect(z); return { x: r.x, y: r.y, width: r.width, height: r.height, zone: z, el: el }; }
  function winnerFor(card) { var avatar = relRect(card); var matches = []; zones.forEach(function (z) { var r = zoneRect(z); if (r && overlaps(avatar, r, -8)) matches.push(r); }); if (!matches.length) return null; matches.sort(function (a, b) { var d = pri(b.zone.priority) - pri(a.zone.priority); return d || String(a.zone.id).localeCompare(String(b.zone.id)); }); return matches[0]; }

  async function fire(card, match) {
    if (!card || !match || !match.zone) return;
    var sessionId = card.dataset.sessionId || '';
    if (!sessionId) return;
    var z = match.zone;
    var key = z.id + ':' + sessionId + ':' + (z.campaign_id || 'none') + ':' + (z.automation_action || 'message_and_reward');
    if (now() - (lastFire.get(key) || 0) < 240000) return;
    lastFire.set(key, now());
    match.el.classList.add('is-hot');
    card.classList.add('is-triggered');
    try {
      var data = payload(await MG.post('/api/merchant-canvas/campaign-trigger-automation.php', { session_id:sessionId, trigger_zone_id:z.id })) || {};
      if (data.cooldown) toast('Trigger cooldown active.', 'info');
      else if (data.reward_sent && data.message_sent) toast('Trigger fired: message + reward sent.', 'success');
      else if (data.reward_sent) toast('Trigger fired: reward sent.', 'success');
      else if (data.message_sent) toast('Trigger fired: message sent.', 'success');
      else if (data.triggered) toast('Trigger fired: ' + actionLabel(data.automation_action || z.automation_action), 'success');
      if (activeAnalyticsZone === String(z.id)) loadAnalytics(String(z.id), false);
    } catch (e) {
      try { var fallback = payload(await MG.post('/api/merchant-canvas/campaign-trigger.php', { session_id:sessionId, trigger_zone_id:z.id, trigger_key:z.trigger_key || 'store_canvas_zone', trigger_label:z.name || 'IN/OUT Box Trigger', campaign_id:z.campaign_id || '', priority:pri(z.priority) })) || {}; if (fallback.reward_sent) toast('Trigger fired and sent reward.', 'success'); else if (fallback.message_sent) toast('Trigger fired and sent message.', 'success'); }
      catch (fallbackError) { lastFire.set(key, 0); }
    }
    window.setTimeout(function () { match.el.classList.remove('is-hot'); card.classList.remove('is-triggered'); }, 4200);
  }
  function scan() { clearLegacy(); Array.from(layer.querySelectorAll('[data-session-id]')).forEach(function (card) { var match = winnerFor(card); if (match) fire(card, match); }); }

  function ensureAnalyticsDrawer() { if (analyticsDrawer && analyticsDrawer.isConnected) return analyticsDrawer; analyticsDrawer = document.createElement('aside'); analyticsDrawer.className = 'mg-canvas-trigger-analytics-drawer'; analyticsDrawer.setAttribute('aria-hidden', 'true'); analyticsDrawer.innerHTML = '<div class="mg-trigger-analytics-head"><div><span>Trigger Analytics</span><h2 data-trigger-analytics-title>Select a trigger</h2></div><button type="button" data-trigger-analytics-close aria-label="Close trigger analytics">×</button></div><div class="mg-trigger-analytics-body" data-trigger-analytics-body><p>Select a trigger zone to review performance.</p></div>'; document.body.appendChild(analyticsDrawer); analyticsDrawer.addEventListener('click', function (event) { if (event.target.closest('[data-trigger-analytics-close]')) closeAnalytics(); }); return analyticsDrawer; }
  function closeAnalytics() { if (!analyticsDrawer) return; analyticsDrawer.classList.remove('is-open'); analyticsDrawer.setAttribute('aria-hidden', 'true'); activeAnalyticsZone = ''; if (analyticsTimer) window.clearInterval(analyticsTimer); analyticsTimer = null; }
  function renderAnalytics(data) { var drawer = ensureAnalyticsDrawer(); var title = drawer.querySelector('[data-trigger-analytics-title]'); var body = drawer.querySelector('[data-trigger-analytics-body]'); var zone = data.zone || {}; var stats = data.stats || {}; var events = Array.isArray(data.events) ? data.events : []; title.textContent = zone.name || 'Trigger zone'; var statCards = [['Fires',stats.fires||0],['Customers',stats.unique_customers||0],['Messages',stats.messages_sent||0],['Rewards',stats.rewards_sent||0],['Stamp debits',stats.stamp_debits||0],['Debit issues',stats.stamp_debit_errors||0]].map(function (item) { return '<article><span>' + esc(item[0]) + '</span><strong>' + esc(item[1]) + '</strong></article>'; }).join(''); var rows = events.length ? events.map(function (event) { var badges = []; if (event.message_sent) badges.push('<b>message</b>'); if (event.reward_sent) badges.push('<b>reward</b>'); if (event.stamp_debited) badges.push('<b>stamp</b>'); if (event.stamp_debit_error) badges.push('<b class="is-warn">stamp issue</b>'); return '<article class="mg-trigger-event"><div><strong>' + esc(event.customer_name || 'Customer') + '</strong><span>' + esc(fmt(event.created_at)) + '</span></div><p>' + esc(event.campaign_title || event.event_label || 'Campaign trigger zone') + '</p><footer>' + (badges.length ? badges.join('') : '<b>event</b>') + '</footer></article>'; }).join('') : '<div class="mg-trigger-analytics-empty">No trigger fires yet.</div>'; body.innerHTML = '<section class="mg-trigger-zone-summary"><div><span>Assigned campaign</span><strong>' + esc(zone.campaign_title || 'No active campaign assigned') + '</strong></div><div><span>Priority</span><strong>' + esc(zone.priority || 3) + '</strong></div><div><span>Action</span><strong>' + esc(actionLabel(zone.automation_action || 'message_and_reward')) + '</strong></div><div><span>Last triggered</span><strong>' + esc(fmt(stats.last_triggered_at || zone.last_triggered_at)) + '</strong></div></section><section class="mg-trigger-stat-grid">' + statCards + '</section><section class="mg-trigger-ledger-note"><strong>Stamp Ledger</strong><span>Automated trigger messages debit <code>' + esc(data.stamp_action_key || 'store_canvas_auto_message_send') + '</code> when Stamps are available.</span></section><section class="mg-trigger-events"><h3>Recent trigger events</h3>' + rows + '</section>'; }
  async function loadAnalytics(zoneId, showLoading) { if (!zoneId || isTmp(zoneById(zoneId))) return; activeAnalyticsZone = zoneId; var drawer = ensureAnalyticsDrawer(); drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false'); if (showLoading !== false) { drawer.querySelector('[data-trigger-analytics-title]').textContent = (zoneById(zoneId) || {}).name || 'Trigger zone'; drawer.querySelector('[data-trigger-analytics-body]').innerHTML = '<div class="mg-trigger-analytics-loading">Loading trigger analytics...</div>'; } try { var data = payload(await MG.get('/api/merchant-canvas/trigger-zone-analytics.php?zone_id=' + encodeURIComponent(zoneId))) || {}; if (activeAnalyticsZone === zoneId) renderAnalytics(data); } catch (e) { if (activeAnalyticsZone === zoneId) drawer.querySelector('[data-trigger-analytics-body]').innerHTML = '<div class="mg-trigger-analytics-error">' + esc(e.message || 'Unable to load trigger analytics.') + '</div>'; } if (analyticsTimer) window.clearInterval(analyticsTimer); analyticsTimer = window.setInterval(function () { if (activeAnalyticsZone === zoneId) loadAnalytics(zoneId, false); }, 12000); }

  function startDrag(e) { if (e.button !== 0 || e.target.closest('select,input,button,label,textarea')) return; var el = e.target.closest('[data-canvas-persistent-zone]'); if (!el) return; var z = zoneById(el.dataset.canvasTriggerZone); if (!z) return; var r = pxRect(z); var b = layer.getBoundingClientRect(); drag = { z:z, el:el, resize:!!e.target.closest('[data-trigger-resize]'), sx:e.clientX, sy:e.clientY, r:r, bw:b.width, bh:b.height, moved:false }; el.setPointerCapture(e.pointerId); el.classList.add('is-editing'); e.preventDefault(); }
  function moveDrag(e) { if (!drag) return; var dx = e.clientX - drag.sx; var dy = e.clientY - drag.sy; if (Math.abs(dx) > 2 || Math.abs(dy) > 2) drag.moved = true; var r = { x:drag.r.x, y:drag.r.y, width:drag.r.width, height:drag.r.height }; if (drag.resize) { r.width = clamp(drag.r.width + dx, 160, Math.max(160, drag.bw - r.x - 8)); r.height = clamp(drag.r.height + dy, 112, Math.max(112, drag.bh - r.y - 8)); } else { r.x = clamp(drag.r.x + dx, 8, Math.max(8, drag.bw - r.width - 8)); r.y = clamp(drag.r.y + dy, 8, Math.max(8, drag.bh - r.height - 8)); } setFromPx(drag.z, r); updateEl(drag.z, drag.el); }
  function endDrag() { if (!drag) return; var z = drag.z; var moved = drag.moved; drag.el.classList.remove('is-editing'); drag = null; if (moved) { lastDragAt = now(); saveZone(z, false); } }

  layer.addEventListener('pointerdown', startDrag);
  document.addEventListener('pointermove', moveDrag);
  document.addEventListener('pointerup', endDrag);
  layer.addEventListener('click', function (e) { var zoneEl = e.target.closest('[data-canvas-persistent-zone]'); var analytics = e.target.closest('[data-trigger-analytics]'); if (analytics) { var az = zoneById(analytics.closest('[data-canvas-persistent-zone]').dataset.canvasTriggerZone); if (az) loadAnalytics(String(az.id), true); return; } var settings = e.target.closest('[data-trigger-settings]'); if (settings) { var sz = zoneById(settings.closest('[data-canvas-persistent-zone]').dataset.canvasTriggerZone); if (sz) openSettings(sz); return; } var del = e.target.closest('[data-trigger-delete]'); if (del) { removeZone(zoneById(del.closest('[data-canvas-persistent-zone]').dataset.canvasTriggerZone)); return; } var tog = e.target.closest('[data-trigger-toggle]'); if (tog) { var z = zoneById(tog.closest('[data-canvas-persistent-zone]').dataset.canvasTriggerZone); if (z) { z.status = z.status === 'paused' ? 'active' : 'paused'; render(); saveZone(z, false); } return; } if (zoneEl && !e.target.closest('select,input,button,label,textarea') && now() - lastDragAt > 250) { var clickedZone = zoneById(zoneEl.dataset.canvasTriggerZone); if (clickedZone) openSettings(clickedZone); } });
  layer.addEventListener('change', function (e) { var el = e.target.closest('[data-canvas-persistent-zone]'); if (!el) return; var z = zoneById(el.dataset.canvasTriggerZone); if (!z) return; if (e.target.matches('[data-trigger-campaign]')) { z.campaign_id = e.target.value || ''; z.campaign_title = campaignTitle(z.campaign_id); } if (e.target.matches('[data-trigger-priority]')) z.priority = pri(e.target.value); render(); saveZone(z, false); });
  window.addEventListener('resize', render);
  ensureButton(); ensureAnalyticsDrawer(); ensureSettingsDrawer(); loadCampaigns(); loadZones(); window.setInterval(scan, 850); window.setInterval(clearLegacy, 1200);
})(window, document);
