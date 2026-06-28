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

  function payload(r) { return r && r.data ? r.data : r; }
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function now() { return Date.now(); }
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; });
  }
  function toast(m, t) { if (MG.toast) MG.toast(m, t || 'info'); }
  function tempId() { return 'tmp-' + Date.now() + '-' + Math.floor(Math.random() * 10000); }
  function pri(v) { return clamp(parseInt(v || 3, 10) || 3, 1, 5); }
  function isTmp(z) { return z && String(z.id || '').indexOf('tmp-') === 0; }

  function clearLegacy() {
    try { window.localStorage.removeItem('mgCanvasTriggerConfig:v2'); } catch (e) {}
    Array.from(map.querySelectorAll('.mg-canvas-trigger-add-btn:not([data-persistent-trigger-button])')).forEach(function (el) { el.remove(); });
    Array.from(layer.querySelectorAll('.mg-canvas-trigger-zone:not([data-canvas-persistent-zone])')).forEach(function (el) { el.hidden = true; el.remove(); });
  }

  function zoneDefaults() {
    return { id: tempId(), name: 'IN/OUT Box Trigger', trigger_key: 'store_canvas_zone', campaign_id: campaigns[0] ? String(campaigns[0].id || '') : '', priority: 3, x: 68, y: 66, width: 28, height: 18, status: 'active', saving: true };
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

  function priorityOptions(current) {
    var currentPriority = pri(current);
    var html = '';
    for (var i = 1; i <= 5; i++) html += '<option value="' + i + '"' + (i === currentPriority ? ' selected' : '') + '>' + i + '</option>';
    return html;
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
    el.innerHTML = '<span class="mg-canvas-trigger-zone-icon">IO</span><span class="mg-canvas-trigger-zone-copy"><span class="mg-canvas-trigger-zone-title"><input data-trigger-name type="text" maxlength="160"></span><span>Transparent campaign trigger square.</span><span class="mg-canvas-trigger-row"><label>Assigned campaign<select data-trigger-campaign></select></label><label class="mg-canvas-trigger-priority">Priority<select data-trigger-priority></select></label></span><em data-trigger-status>Drag or resize anywhere on the canvas.</em></span><span class="mg-canvas-trigger-actions"><button type="button" data-trigger-toggle title="Enable or pause trigger">●</button><button type="button" data-trigger-delete title="Delete trigger">×</button></span><span class="mg-canvas-trigger-drag-hint">Drag zone</span><span class="mg-canvas-trigger-resize" data-trigger-resize aria-hidden="true"></span>';
    layer.appendChild(el);
    els.set(id, el);
    return el;
  }

  function updateEl(z, el) {
    el.dataset.canvasTriggerZone = String(z.id || '');
    el.classList.toggle('is-paused', z.status === 'paused');
    el.classList.toggle('is-saving', !!z.saving);
    var r = pxRect(z);
    el.style.left = Math.round(r.x) + 'px';
    el.style.top = Math.round(r.y) + 'px';
    el.style.width = Math.round(r.width) + 'px';
    el.style.height = Math.round(r.height) + 'px';
    var name = el.querySelector('[data-trigger-name]');
    var campaign = el.querySelector('[data-trigger-campaign]');
    var priority = el.querySelector('[data-trigger-priority]');
    var status = el.querySelector('[data-trigger-status]');
    var toggle = el.querySelector('[data-trigger-toggle]');
    if (name && name.value !== String(z.name || '')) name.value = String(z.name || 'IN/OUT Box Trigger');
    if (campaign) { campaign.disabled = !campaignLoaded || !campaigns.length; campaign.innerHTML = campaignOptions(z.campaign_id); }
    if (priority) priority.innerHTML = priorityOptions(z.priority);
    if (toggle) toggle.textContent = z.status === 'paused' ? '○' : '●';
    if (status) status.textContent = z.saving ? 'Saving trigger zone...' : (z.status === 'paused' ? 'Paused. Customer avatars will not fire this trigger.' : 'Priority ' + pri(z.priority) + '. Overlaps use the highest priority zone.');
  }

  function render() {
    ensureButton();
    var active = new Set(zones.map(function (z) { return String(z.id || ''); }));
    els.forEach(function (el, id) { if (!active.has(id)) { el.remove(); els.delete(id); } });
    zones.forEach(function (z) { updateEl(z, ensureZoneEl(z)); });
  }

  function replaceZones(list) {
    zones = Array.isArray(list) ? list.map(function (z) {
      return { id: String(z.id || tempId()), name: String(z.name || 'IN/OUT Box Trigger'), trigger_key: String(z.trigger_key || 'store_canvas_zone'), campaign_id: String(z.campaign_id || ''), priority: pri(z.priority), x: Number(z.x || 0), y: Number(z.y || 0), width: Number(z.width || 28), height: Number(z.height || 18), status: String(z.status || 'active'), saving: false };
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
    try {
      var data = payload(await MG.get('/api/merchant-canvas/trigger-zones.php')) || {};
      replaceZones(data.zones || []);
    } catch (e) { replaceZones([]); }
  }

  function zonePayload(z) {
    return { id: isTmp(z) ? '' : z.id, name: z.name || 'IN/OUT Box Trigger', trigger_key: z.trigger_key || 'store_canvas_zone', campaign_id: z.campaign_id || '', priority: pri(z.priority), x: Number(z.x || 0), y: Number(z.y || 0), width: Number(z.width || 28), height: Number(z.height || 18), status: z.status || 'active' };
  }

  async function saveZone(z, created) {
    z.saving = true;
    render();
    try {
      var oldId = String(z.id || '');
      var data = payload(await MG.post('/api/merchant-canvas/trigger-zone-save.php', zonePayload(z))) || {};
      if (Array.isArray(data.zones)) replaceZones(data.zones);
      else if (data.zone) {
        if (isTmp(z)) { var oldEl = els.get(oldId); if (oldEl) oldEl.remove(); els.delete(oldId); }
        replaceZones(zones.map(function (item) { return String(item.id) === oldId ? data.zone : item; }));
      }
      if (created) toast('Trigger saved. Set campaign and priority.', 'success');
    } catch (e) { z.saving = false; render(); toast(e.message || 'Unable to save trigger zone.', 'error'); }
  }

  async function removeZone(z) {
    if (!z || !window.confirm('Delete this trigger zone?')) return;
    if (isTmp(z)) { zones = zones.filter(function (item) { return item !== z; }); render(); return; }
    try {
      var data = payload(await MG.post('/api/merchant-canvas/trigger-zone-delete.php', { id: z.id })) || {};
      if (Array.isArray(data.zones)) replaceZones(data.zones); else { zones = zones.filter(function (item) { return String(item.id) !== String(z.id); }); render(); }
      toast('Trigger zone deleted.', 'success');
    } catch (e) { toast(e.message || 'Unable to delete trigger zone.', 'error'); }
  }

  function overlaps(a, b, pad) { pad = pad || 0; return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y; }
  function relRect(el) { var r = el.getBoundingClientRect(); var b = layer.getBoundingClientRect(); return { x: r.left - b.left, y: r.top - b.top, width: r.width, height: r.height }; }
  function zoneRect(z) { if (!z || z.status === 'paused' || isTmp(z) || z.saving) return null; var el = els.get(String(z.id)); if (!el) return null; var r = pxRect(z); return { x: r.x, y: r.y, width: r.width, height: r.height, zone: z, el: el }; }

  function winnerFor(card) {
    var avatar = relRect(card);
    var matches = [];
    zones.forEach(function (z) { var r = zoneRect(z); if (r && overlaps(avatar, r, -8)) matches.push(r); });
    if (!matches.length) return null;
    matches.sort(function (a, b) { var d = pri(b.zone.priority) - pri(a.zone.priority); return d || String(a.zone.id).localeCompare(String(b.zone.id)); });
    return matches[0];
  }

  async function fire(card, match) {
    if (!card || !match || !match.zone) return;
    var sessionId = card.dataset.sessionId || '';
    if (!sessionId) return;
    var z = match.zone;
    var key = z.id + ':' + sessionId + ':' + (z.campaign_id || 'none');
    if (now() - (lastFire.get(key) || 0) < 240000) return;
    lastFire.set(key, now());
    match.el.classList.add('is-hot');
    card.classList.add('is-triggered');
    try {
      var data = payload(await MG.post('/api/merchant-canvas/campaign-trigger.php', { session_id: sessionId, trigger_zone_id: z.id, trigger_key: z.trigger_key || 'store_canvas_zone', trigger_label: z.name || 'IN/OUT Box Trigger', campaign_id: z.campaign_id || '', priority: pri(z.priority) })) || {};
      if (data.reward_sent) toast('Priority ' + pri(z.priority) + ' trigger fired and sent reward.', 'success');
      else if (data.message_sent) toast('Priority ' + pri(z.priority) + ' trigger fired and sent message.', 'success');
    } catch (e) { lastFire.set(key, 0); }
    window.setTimeout(function () { match.el.classList.remove('is-hot'); card.classList.remove('is-triggered'); }, 4200);
  }

  function scan() {
    clearLegacy();
    Array.from(layer.querySelectorAll('[data-session-id]')).forEach(function (card) { var match = winnerFor(card); if (match) fire(card, match); });
  }

  function startDrag(e) {
    if (e.button !== 0 || e.target.closest('select,input,button,label')) return;
    var el = e.target.closest('[data-canvas-persistent-zone]');
    if (!el) return;
    var z = zoneById(el.dataset.canvasTriggerZone); if (!z) return;
    var r = pxRect(z); var b = layer.getBoundingClientRect();
    drag = { z: z, el: el, resize: !!e.target.closest('[data-trigger-resize]'), sx: e.clientX, sy: e.clientY, r: r, bw: b.width, bh: b.height };
    el.setPointerCapture(e.pointerId); el.classList.add('is-editing'); e.preventDefault();
  }
  function moveDrag(e) {
    if (!drag) return;
    var dx = e.clientX - drag.sx; var dy = e.clientY - drag.sy;
    var r = { x: drag.r.x, y: drag.r.y, width: drag.r.width, height: drag.r.height };
    if (drag.resize) { r.width = clamp(drag.r.width + dx, 160, Math.max(160, drag.bw - r.x - 8)); r.height = clamp(drag.r.height + dy, 112, Math.max(112, drag.bh - r.y - 8)); }
    else { r.x = clamp(drag.r.x + dx, 8, Math.max(8, drag.bw - r.width - 8)); r.y = clamp(drag.r.y + dy, 8, Math.max(8, drag.bh - r.height - 8)); }
    setFromPx(drag.z, r); updateEl(drag.z, drag.el);
  }
  function endDrag() { if (!drag) return; var z = drag.z; drag.el.classList.remove('is-editing'); drag = null; saveZone(z, false); }

  layer.addEventListener('pointerdown', startDrag);
  document.addEventListener('pointermove', moveDrag);
  document.addEventListener('pointerup', endDrag);
  layer.addEventListener('click', function (e) { var del = e.target.closest('[data-trigger-delete]'); if (del) removeZone(zoneById(del.closest('[data-canvas-persistent-zone]').dataset.canvasTriggerZone)); var tog = e.target.closest('[data-trigger-toggle]'); if (tog) { var z = zoneById(tog.closest('[data-canvas-persistent-zone]').dataset.canvasTriggerZone); if (z) { z.status = z.status === 'paused' ? 'active' : 'paused'; render(); saveZone(z, false); } } });
  layer.addEventListener('change', function (e) { var el = e.target.closest('[data-canvas-persistent-zone]'); if (!el) return; var z = zoneById(el.dataset.canvasTriggerZone); if (!z) return; if (e.target.matches('[data-trigger-campaign]')) z.campaign_id = e.target.value || ''; if (e.target.matches('[data-trigger-priority]')) z.priority = pri(e.target.value); if (e.target.matches('[data-trigger-name]')) z.name = e.target.value.trim() || 'IN/OUT Box Trigger'; render(); saveZone(z, false); });
  layer.addEventListener('focusout', function (e) { if (!e.target.matches('[data-trigger-name]')) return; var el = e.target.closest('[data-canvas-persistent-zone]'); var z = el ? zoneById(el.dataset.canvasTriggerZone) : null; if (z) { z.name = e.target.value.trim() || 'IN/OUT Box Trigger'; saveZone(z, false); } });
  window.addEventListener('resize', render);
  ensureButton(); loadCampaigns(); loadZones(); window.setInterval(scan, 850); window.setInterval(clearLegacy, 1200);
})(window, document);
