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
  var campaignsReady = false;
  var zones = [];
  var nodes = new Map();
  var addButton = null;
  var drawer = null;
  var activeId = '';
  var drag = null;
  var saveTimer = null;
  var lastDragAt = 0;
  var recentFire = new Map();

  var defaultMessage = 'Hi {first_name} — I noticed you crossed the {trigger_name} zone. I can help with {campaign_title}.';

  function payload(response) { return response && response.data ? response.data : response; }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]; }); }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function now() { return Date.now(); }
  function toast(message, type) { if (MG.toast) MG.toast(message, type || 'info'); }
  function tempId() { return 'tmp-' + Date.now() + '-' + Math.floor(Math.random() * 10000); }
  function isTemp(zone) { return zone && String(zone.id || '').indexOf('tmp-') === 0; }
  function priority(value) { return clamp(parseInt(value || 3, 10) || 3, 1, 5); }
  function boolInt(value) { return value === true || value === 1 || value === '1' || value === 'true' || value === 'on' ? 1 : 0; }
  function canvasRect() { return map.getBoundingClientRect(); }

  function zoneById(id) {
    return zones.find(function (zone) { return String(zone.id || '') === String(id || ''); }) || null;
  }

  function campaignTitle(id) {
    var found = campaigns.find(function (campaign) { return String(campaign.id || '') === String(id || ''); });
    return found ? String(found.title || 'Campaign') : '';
  }

  function campaignOptions(current) {
    if (!campaignsReady) return '<option value="">Loading campaigns...</option>';
    var html = '<option value="">No campaign assigned</option>';
    campaigns.forEach(function (campaign) {
      var id = String(campaign.id || '');
      var label = String(campaign.title || 'Campaign');
      if (campaign.reward_template_title) label += ' - ' + String(campaign.reward_template_title);
      html += '<option value="' + esc(id) + '"' + (id === String(current || '') ? ' selected' : '') + '>' + esc(label) + '</option>';
    });
    return html;
  }

  function options(items, current) {
    return items.map(function (item) {
      return '<option value="' + esc(item[0]) + '"' + (String(item[0]) === String(current || '') ? ' selected' : '') + '>' + esc(item[1]) + '</option>';
    }).join('');
  }

  function rectsOverlap(a, b, pad) {
    pad = pad || 0;
    return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y;
  }

  function pixelRect(zone) {
    var box = canvasRect();
    var width = clamp(box.width * (Number(zone.width || 16) / 100), 130, Math.max(130, box.width - 16));
    var height = clamp(box.height * (Number(zone.height || 10) / 100), 74, Math.max(74, box.height - 16));
    var x = clamp(box.width * (Number(zone.x || 0) / 100), 8, Math.max(8, box.width - width - 8));
    var y = clamp(box.height * (Number(zone.y || 0) / 100), 8, Math.max(8, box.height - height - 8));
    return { x:x, y:y, width:width, height:height };
  }

  function savePixelRect(zone, rect) {
    var box = canvasRect();
    zone.width = clamp((rect.width / box.width) * 100, 4, 100);
    zone.height = clamp((rect.height / box.height) * 100, 4, 100);
    zone.x = clamp((rect.x / box.width) * 100, 0, Math.max(0, 100 - zone.width));
    zone.y = clamp((rect.y / box.height) * 100, 0, Math.max(0, 100 - zone.height));
  }

  function nextPlacement() {
    var size = { width:16, height:10 };
    var candidates = [];
    [8, 28, 48, 68].forEach(function (x) {
      [10, 26, 42, 58, 74].forEach(function (y) {
        candidates.push({ x:x, y:y, width:size.width, height:size.height });
      });
    });
    var existing = zones.map(function (zone) {
      return { x:Number(zone.x || 0), y:Number(zone.y || 0), width:Number(zone.width || size.width), height:Number(zone.height || size.height) };
    });
    for (var i = 0; i < candidates.length; i++) {
      var candidate = candidates[i];
      var blocked = existing.some(function (item) { return rectsOverlap(candidate, item, 3); });
      if (!blocked) return candidate;
    }
    var fallback = candidates[zones.length % candidates.length];
    return { x:clamp(fallback.x + (zones.length % 3) * 3, 2, 78), y:clamp(fallback.y + (zones.length % 4) * 3, 2, 82), width:size.width, height:size.height };
  }

  function normalizeZone(raw) {
    raw = raw || {};
    return {
      id: String(raw.id || tempId()),
      name: String(raw.name || 'Trigger Zone'),
      trigger_key: String(raw.trigger_key || 'store_canvas_zone'),
      campaign_id: String(raw.campaign_id || ''),
      campaign_title: String(raw.campaign_title || ''),
      priority: priority(raw.priority),
      x: Number(raw.x || 0),
      y: Number(raw.y || 0),
      width: Number(raw.width || 16),
      height: Number(raw.height || 10),
      status: String(raw.status || 'active'),
      automation_action: String(raw.automation_action || 'message_and_reward'),
      cooldown_policy: String(raw.cooldown_policy || 'fifteen_minutes'),
      cooldown_seconds: Math.max(60, parseInt(raw.cooldown_seconds || 900, 10) || 900),
      auto_message_text: String(raw.auto_message_text || defaultMessage),
      fallback_action: String(raw.fallback_action || 'notify_only'),
      crm_segment_name: String(raw.crm_segment_name || ''),
      notify_merchant: boolInt(raw.notify_merchant == null ? 1 : raw.notify_merchant),
      last_triggered_at: raw.last_triggered_at || null,
      saving: false
    };
  }

  function separateOverlaps() {
    var moved = false;
    for (var i = 0; i < zones.length; i++) {
      var current = zones[i];
      var currentRect = { x:Number(current.x || 0), y:Number(current.y || 0), width:Number(current.width || 16), height:Number(current.height || 10) };
      for (var j = 0; j < i; j++) {
        var previous = zones[j];
        var previousRect = { x:Number(previous.x || 0), y:Number(previous.y || 0), width:Number(previous.width || 16), height:Number(previous.height || 10) };
        if (rectsOverlap(currentRect, previousRect, 2)) {
          var placement = nextPlacement();
          current.x = placement.x;
          current.y = placement.y;
          current.width = current.width || placement.width;
          current.height = current.height || placement.height;
          moved = true;
          break;
        }
      }
    }
    return moved;
  }

  function newZone() {
    var placement = nextPlacement();
    var count = zones.length + 1;
    return {
      id: tempId(),
      name: 'Trigger Zone ' + count,
      trigger_key: 'store_canvas_zone_' + count,
      campaign_id: campaigns[0] ? String(campaigns[0].id || '') : '',
      campaign_title: campaigns[0] ? String(campaigns[0].title || '') : '',
      priority: 3,
      x: placement.x,
      y: placement.y,
      width: placement.width,
      height: placement.height,
      status: 'active',
      automation_action: 'message_and_reward',
      cooldown_policy: 'fifteen_minutes',
      cooldown_seconds: 900,
      auto_message_text: defaultMessage,
      fallback_action: 'notify_only',
      crm_segment_name: '',
      notify_merchant: 1,
      saving: false
    };
  }

  function payloadFor(zone) {
    return {
      id: isTemp(zone) ? '' : zone.id,
      name: zone.name || 'Trigger Zone',
      trigger_key: zone.trigger_key || 'store_canvas_zone',
      campaign_id: zone.campaign_id || '',
      priority: priority(zone.priority),
      x: Number(zone.x || 0),
      y: Number(zone.y || 0),
      width: Number(zone.width || 16),
      height: Number(zone.height || 10),
      status: zone.status || 'active',
      automation_action: zone.automation_action || 'message_and_reward',
      cooldown_policy: zone.cooldown_policy || 'fifteen_minutes',
      cooldown_seconds: Math.max(60, parseInt(zone.cooldown_seconds || 900, 10) || 900),
      auto_message_text: zone.auto_message_text || '',
      fallback_action: zone.fallback_action || 'notify_only',
      crm_segment_name: zone.crm_segment_name || '',
      notify_merchant: boolInt(zone.notify_merchant == null ? 1 : zone.notify_merchant)
    };
  }

  function ensureAddButton() {
    if (addButton && addButton.isConnected) return addButton;
    addButton = document.createElement('button');
    addButton.type = 'button';
    addButton.className = 'mg-canvas-trigger-add-btn';
    addButton.setAttribute('data-persistent-trigger-button', '1');
    addButton.innerHTML = '<span>+</span> Trigger';
    map.appendChild(addButton);
    addButton.addEventListener('click', function () {
      var zone = newZone();
      zones.push(zone);
      render();
      openDrawer(zone);
      saveZone(zone, true);
    });
    return addButton;
  }

  function ensureZoneNode(zone) {
    var id = String(zone.id || '');
    var node = nodes.get(id);
    if (node && node.isConnected) return node;
    node = document.createElement('div');
    node.className = 'mg-canvas-trigger-zone';
    node.setAttribute('data-canvas-persistent-zone', '1');
    node.setAttribute('data-canvas-trigger-zone', id);
    node.setAttribute('role', 'button');
    node.setAttribute('tabindex', '0');
    node.innerHTML = '<span class="mg-canvas-trigger-main"><strong data-zone-name></strong><small data-zone-campaign></small></span><span class="mg-canvas-trigger-actions"><button type="button" class="mg-canvas-trigger-settings-icon" data-trigger-settings aria-label="Open trigger settings">⚙</button></span><span class="mg-canvas-trigger-resize" data-trigger-resize aria-hidden="true"></span>';
    layer.appendChild(node);
    nodes.set(id, node);
    return node;
  }

  function updateZoneNode(zone, node) {
    node.hidden = false;
    node.style.visibility = 'visible';
    node.dataset.canvasTriggerZone = String(zone.id || '');
    node.classList.toggle('is-paused', zone.status === 'paused');
    node.classList.toggle('is-saving', !!zone.saving);
    node.classList.toggle('is-settings-open', String(zone.id || '') === activeId);
    node.style.zIndex = String(zone.id || '') === activeId ? '15' : '5';
    var rect = pixelRect(zone);
    node.style.left = Math.round(rect.x) + 'px';
    node.style.top = Math.round(rect.y) + 'px';
    node.style.width = Math.round(rect.width) + 'px';
    node.style.height = Math.round(rect.height) + 'px';
    var name = node.querySelector('[data-zone-name]');
    var campaign = node.querySelector('[data-zone-campaign]');
    if (name) name.textContent = zone.name || 'Trigger Zone';
    if (campaign) campaign.textContent = zone.campaign_title || campaignTitle(zone.campaign_id) || 'No campaign assigned';
  }

  function render() {
    ensureAddButton();
    var active = new Set(zones.map(function (zone) { return String(zone.id || ''); }));
    nodes.forEach(function (node, id) {
      if (!active.has(id)) { node.remove(); nodes.delete(id); }
      else { node.hidden = false; node.style.visibility = 'visible'; }
    });
    zones.forEach(function (zone) { updateZoneNode(zone, ensureZoneNode(zone)); });
  }

  function setDrawerStatus(message, state) {
    if (!drawer) return;
    var status = drawer.querySelector('[data-trigger-settings-status]');
    if (!status) return;
    status.textContent = message || '';
    status.className = 'mg-trigger-settings-status' + (state ? ' is-' + state : '');
  }

  function ensureDrawer() {
    if (drawer && drawer.isConnected) return drawer;
    drawer = document.createElement('aside');
    drawer.className = 'mg-canvas-trigger-settings-drawer';
    drawer.setAttribute('aria-hidden', 'true');
    drawer.style.zIndex = '100000';
    drawer.innerHTML = '<div class="mg-trigger-settings-head"><div><span>Trigger Settings</span><h2 data-trigger-settings-title>Trigger Zone</h2></div><button type="button" data-trigger-settings-close aria-label="Close trigger settings">x</button></div><div class="mg-trigger-settings-body" data-trigger-settings-body></div><div class="mg-trigger-settings-foot"><p class="mg-trigger-settings-status" data-trigger-settings-status role="status"></p><button type="button" class="mg-btn mg-btn-primary" data-trigger-settings-save>Save Settings</button></div>';
    document.body.appendChild(drawer);
    drawer.addEventListener('click', function (event) {
      if (event.target.closest('[data-trigger-settings-close]')) return closeDrawer();
      if (event.target.closest('[data-trigger-settings-save]')) { var zone = zoneById(activeId); if (zone) saveZone(zone, false); }
      if (event.target.closest('[data-trigger-settings-delete]')) deleteZone(zoneById(activeId));
    });
    drawer.addEventListener('change', readAndSchedule);
    drawer.addEventListener('input', function (event) {
      if (event.target.matches('input,textarea')) readAndSchedule(event);
    });
    return drawer;
  }

  function closeOtherDrawers() {
    document.querySelectorAll('.mg-canvas-crm-drawer,.mg-canvas-merchant-settings-drawer,.mg-canvas-trigger-analytics-drawer').forEach(function (item) {
      item.classList.remove('is-open');
      item.setAttribute('aria-hidden', 'true');
    });
  }

  function openDrawer(zone) {
    if (!zone) return;
    activeId = String(zone.id || '');
    closeOtherDrawers();
    ensureDrawer();
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    drawer.style.zIndex = '100000';
    renderDrawer(zone);
    render();
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    activeId = '';
    if (saveTimer) window.clearTimeout(saveTimer);
    saveTimer = null;
    render();
  }

  function renderDrawer(zone) {
    var title = drawer.querySelector('[data-trigger-settings-title]');
    var body = drawer.querySelector('[data-trigger-settings-body]');
    if (title) title.textContent = zone.name || 'Trigger Zone';
    if (!body) return;
    body.innerHTML = '<form class="mg-trigger-settings-form" data-trigger-settings-form>' +
      '<label>Trigger name<input name="name" maxlength="160" value="' + esc(zone.name || 'Trigger Zone') + '"></label>' +
      '<label>Assigned campaign<select name="campaign_id">' + campaignOptions(zone.campaign_id) + '</select></label>' +
      '<div class="mg-trigger-settings-row"><label>Priority<select name="priority">' + options([['1','1'],['2','2'],['3','3'],['4','4'],['5','5']], zone.priority) + '</select></label><label>Status<select name="status">' + options([['active','Active'],['paused','Paused']], zone.status || 'active') + '</select></label></div>' +
      '<label>Automation action<select name="automation_action">' + options([['message_and_reward','Message + reward'],['message_only','Message only'],['reward_only','Reward only'],['notify_only','Notify merchant'],['follow_up','Follow-up'],['crm_segment','CRM segment'],['analytics_only','Analytics only']], zone.automation_action || 'message_and_reward') + '</select></label>' +
      '<div class="mg-trigger-settings-row"><label>Cooldown<select name="cooldown_policy">' + options([['five_minutes','Five minutes'],['fifteen_minutes','Fifteen minutes'],['one_hour','One hour'],['once_per_visit','Once per visit'],['once_per_customer_day','Once per customer per day']], zone.cooldown_policy || 'fifteen_minutes') + '</select></label><label>Seconds<input name="cooldown_seconds" type="number" min="60" max="86400" step="60" value="' + esc(zone.cooldown_seconds || 900) + '"></label></div>' +
      '<label>Auto message<textarea name="auto_message_text" rows="5" maxlength="1000">' + esc(zone.auto_message_text || '') + '</textarea><small>Tokens: {first_name}, {trigger_name}, {campaign_title}</small></label>' +
      '<div class="mg-trigger-settings-row"><label>Fallback<select name="fallback_action">' + options([['notify_only','Notify merchant'],['analytics_only','Analytics only'],['skip','Skip']], zone.fallback_action || 'notify_only') + '</select></label><label>CRM segment<input name="crm_segment_name" maxlength="160" value="' + esc(zone.crm_segment_name || '') + '"></label></div>' +
      '<label class="mg-trigger-settings-check"><input type="checkbox" name="notify_merchant" value="1"' + (boolInt(zone.notify_merchant) ? ' checked' : '') + '> Notify merchant when this trigger fires</label>' +
      '<section class="mg-trigger-settings-actions"><button type="button" data-trigger-settings-delete>Delete Trigger</button></section>' +
      '</form>';
  }

  function readForm(form, zone) {
    if (!form || !zone) return;
    zone.name = (form.elements.name ? form.elements.name.value.trim() : zone.name) || 'Trigger Zone';
    zone.trigger_key = zone.trigger_key || 'store_canvas_zone';
    zone.campaign_id = form.elements.campaign_id ? form.elements.campaign_id.value || '' : zone.campaign_id || '';
    zone.campaign_title = campaignTitle(zone.campaign_id);
    zone.priority = form.elements.priority ? priority(form.elements.priority.value) : priority(zone.priority);
    zone.status = form.elements.status ? form.elements.status.value || 'active' : zone.status || 'active';
    zone.automation_action = form.elements.automation_action ? form.elements.automation_action.value || 'message_and_reward' : zone.automation_action || 'message_and_reward';
    zone.cooldown_policy = form.elements.cooldown_policy ? form.elements.cooldown_policy.value || 'fifteen_minutes' : zone.cooldown_policy || 'fifteen_minutes';
    zone.cooldown_seconds = form.elements.cooldown_seconds ? Math.max(60, parseInt(form.elements.cooldown_seconds.value || 900, 10) || 900) : zone.cooldown_seconds || 900;
    zone.auto_message_text = form.elements.auto_message_text ? form.elements.auto_message_text.value.trim() : zone.auto_message_text || '';
    zone.fallback_action = form.elements.fallback_action ? form.elements.fallback_action.value || 'notify_only' : zone.fallback_action || 'notify_only';
    zone.crm_segment_name = form.elements.crm_segment_name ? form.elements.crm_segment_name.value.trim() : zone.crm_segment_name || '';
    zone.notify_merchant = form.elements.notify_merchant && form.elements.notify_merchant.checked ? 1 : 0;
  }

  function readAndSchedule(event) {
    var form = event.target.closest('[data-trigger-settings-form]');
    var zone = zoneById(activeId);
    if (!form || !zone) return;
    readForm(form, zone);
    renderDrawer(zone);
    render();
    setDrawerStatus('Unsaved changes...', 'saving');
    if (saveTimer) window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(function () { saveZone(zone, false); }, 700);
  }

  async function saveZone(zone, created) {
    if (!zone) return;
    zone.saving = true;
    render();
    setDrawerStatus('Saving...', 'saving');
    try {
      var oldId = String(zone.id || '');
      var data = payload(await MG.post('/api/merchant-canvas/trigger-zone-save.php', payloadFor(zone))) || {};
      if (data.zone) {
        var saved = normalizeZone(data.zone);
        if (isTemp(zone)) {
          var oldNode = nodes.get(oldId);
          if (oldNode) oldNode.remove();
          nodes.delete(oldId);
          activeId = saved.id;
        }
        var replaced = false;
        zones = zones.map(function (item) {
          if (String(item.id || '') === oldId || String(item.id || '') === String(saved.id || '')) { replaced = true; return saved; }
          return item;
        });
        if (!replaced) zones.push(saved);
      } else if (Array.isArray(data.zones)) {
        zones = data.zones.map(normalizeZone);
      }
      separateOverlaps();
      setDrawerStatus('Saved.', 'success');
      if (created) toast('Trigger zone added.', 'success');
    } catch (error) {
      zone.saving = false;
      setDrawerStatus(error.message || 'Unable to save.', 'error');
      toast(error.message || 'Unable to save trigger zone.', 'error');
    }
    render();
  }

  async function deleteZone(zone) {
    if (!zone || !window.confirm('Delete this trigger zone?')) return;
    closeDrawer();
    if (isTemp(zone)) {
      zones = zones.filter(function (item) { return item !== zone; });
      render();
      return;
    }
    try {
      var data = payload(await MG.post('/api/merchant-canvas/trigger-zone-delete.php', { id: zone.id })) || {};
      zones = Array.isArray(data.zones) ? data.zones.map(normalizeZone) : zones.filter(function (item) { return String(item.id) !== String(zone.id); });
      toast('Trigger zone deleted.', 'success');
    } catch (error) {
      toast(error.message || 'Unable to delete trigger zone.', 'error');
    }
    render();
  }

  function beginDrag(event) {
    if (event.button !== 0) return;
    if (event.target.closest('select,input,button,label,textarea')) return;
    var node = event.target.closest('[data-canvas-persistent-zone]');
    if (!node) return;
    var zone = zoneById(node.dataset.canvasTriggerZone);
    if (!zone) return;
    var rect = pixelRect(zone);
    var box = canvasRect();
    drag = { zone:zone, node:node, resize:!!event.target.closest('[data-trigger-resize]'), sx:event.clientX, sy:event.clientY, rect:rect, bw:box.width, bh:box.height, moved:false };
    if (typeof node.setPointerCapture === 'function') node.setPointerCapture(event.pointerId);
    node.classList.add('is-editing');
    node.style.zIndex = '20';
    event.preventDefault();
  }

  function moveDrag(event) {
    if (!drag) return;
    var dx = event.clientX - drag.sx;
    var dy = event.clientY - drag.sy;
    if (Math.abs(dx) > 2 || Math.abs(dy) > 2) drag.moved = true;
    var rect = { x:drag.rect.x, y:drag.rect.y, width:drag.rect.width, height:drag.rect.height };
    if (drag.resize) {
      rect.width = clamp(drag.rect.width + dx, 130, Math.max(130, drag.bw - rect.x - 8));
      rect.height = clamp(drag.rect.height + dy, 74, Math.max(74, drag.bh - rect.y - 8));
    } else {
      rect.x = clamp(drag.rect.x + dx, 8, Math.max(8, drag.bw - rect.width - 8));
      rect.y = clamp(drag.rect.y + dy, 8, Math.max(8, drag.bh - rect.height - 8));
    }
    savePixelRect(drag.zone, rect);
    updateZoneNode(drag.zone, drag.node);
  }

  function endDrag() {
    if (!drag) return;
    var zone = drag.zone;
    var moved = drag.moved;
    drag.node.classList.remove('is-editing');
    drag = null;
    if (moved) {
      lastDragAt = now();
      saveZone(zone, false);
    } else {
      render();
    }
  }

  function relRect(node) {
    var rect = node.getBoundingClientRect();
    var box = canvasRect();
    return { x:rect.left - box.left, y:rect.top - box.top, width:rect.width, height:rect.height };
  }

  function scan() {
    Array.from(layer.querySelectorAll('[data-session-id]')).forEach(function (card) {
      var avatar = relRect(card);
      zones.forEach(function (zone) {
        if (zone.status === 'paused' || isTemp(zone) || zone.saving) return;
        var node = nodes.get(String(zone.id));
        if (!node) return;
        var rect = pixelRect(zone);
        if (rectsOverlap(avatar, rect, -8)) fire(card, zone, node);
      });
    });
  }

  async function fire(card, zone, node) {
    var sessionId = card.dataset.sessionId || '';
    if (!sessionId) return;
    var key = String(zone.id) + ':' + sessionId;
    if (now() - (recentFire.get(key) || 0) < 240000) return;
    recentFire.set(key, now());
    node.hidden = false;
    node.style.visibility = 'visible';
    node.classList.add('is-hot');
    card.classList.add('is-triggered');
    try {
      var data = payload(await MG.post('/api/merchant-canvas/campaign-trigger-automation.php', { session_id:sessionId, trigger_zone_id:zone.id })) || {};
      if (data.cooldown) {
        node.classList.add('is-cooldown');
        toast('Trigger cooldown active.', 'info');
      } else {
        node.classList.remove('is-cooldown');
        toast('Trigger fired.', 'success');
      }
    } catch (error) {
      try { await MG.post('/api/merchant-canvas/campaign-trigger.php', { session_id:sessionId, trigger_zone_id:zone.id, trigger_key:zone.trigger_key || 'store_canvas_zone', trigger_label:zone.name || 'Trigger Zone', campaign_id:zone.campaign_id || '', priority:priority(zone.priority) }); }
      catch (fallbackError) { recentFire.set(key, 0); }
    }
    window.setTimeout(function () { node.classList.remove('is-hot'); card.classList.remove('is-triggered'); }, 3200);
  }

  async function loadCampaigns() {
    if (!MG.get) { campaignsReady = true; render(); return; }
    try {
      var data = payload(await MG.get('/api/merchant-canvas/reward-options.php')) || {};
      campaigns = Array.isArray(data.campaigns) ? data.campaigns.filter(function (campaign) { return campaign && campaign.available !== false; }) : [];
    } catch (error) {
      campaigns = [];
    }
    campaignsReady = true;
    render();
  }

  async function loadZones() {
    if (!MG.get) { render(); return; }
    try {
      var data = payload(await MG.get('/api/merchant-canvas/trigger-zones.php')) || {};
      zones = Array.isArray(data.zones) ? data.zones.map(normalizeZone) : [];
      separateOverlaps();
    } catch (error) {
      zones = [];
    }
    render();
  }

  layer.addEventListener('pointerdown', beginDrag);
  document.addEventListener('pointermove', moveDrag);
  document.addEventListener('pointerup', endDrag);

  layer.addEventListener('click', function (event) {
    var node = event.target.closest('[data-canvas-persistent-zone]');
    if (!node) return;
    var zone = zoneById(node.dataset.canvasTriggerZone);
    if (!zone) return;
    if (event.target.closest('[data-trigger-settings]')) { openDrawer(zone); return; }
    if (event.target.closest('select,input,label,textarea,[data-trigger-resize]')) return;
    if (now() - lastDragAt < 220) return;
    openDrawer(zone);
  });

  layer.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    var node = event.target.closest('[data-canvas-persistent-zone]');
    if (!node) return;
    var zone = zoneById(node.dataset.canvasTriggerZone);
    if (!zone) return;
    event.preventDefault();
    openDrawer(zone);
  });

  window.addEventListener('resize', render);
  ensureAddButton();
  ensureDrawer();
  loadCampaigns();
  loadZones();
  window.setInterval(scan, 900);
})(window, document);
