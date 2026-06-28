window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.post) return;

  var map = root.querySelector('[data-canvas-map]');
  var layer = root.querySelector('[data-canvas-customers]');
  var merchantCard = root.querySelector('.mg-canvas-merchant-node');
  if (!map || !layer) return;

  var nodes = new Map();
  var lastTalk = new Map();
  var lastTrigger = new Map();
  var merchant = null;
  var triggerZone = null;
  var triggerButton = null;
  var animationFrame = null;
  var lastTick = 0;
  var storageKey = 'mgCanvasMotion:v2';
  var triggerConfigKey = 'mgCanvasTriggerConfig:v2';
  var saved = {};
  var triggerCampaigns = [];
  var triggerCampaignLoaded = false;
  var dragState = null;
  var triggerConfig = { exists: false, x: 0, y: 0, width: 280, height: 130, campaign_id: '' };

  try { saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}') || {}; } catch (error) { saved = {}; }
  try { triggerConfig = Object.assign(triggerConfig, JSON.parse(window.localStorage.getItem(triggerConfigKey) || '{}') || {}); } catch (error) {}

  function payload(response) { return response && response.data ? response.data : response; }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function now() { return Date.now(); }
  function rand(min, max) { return min + Math.random() * (max - min); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function saveState() {
    var out = { customers: {}, merchant: merchant ? { x: merchant.x, y: merchant.y, vx: merchant.vx, vy: merchant.vy } : null };
    nodes.forEach(function (node, id) {
      out.customers[id] = { x: node.x, y: node.y, vx: node.vx, vy: node.vy, welcomed: node.welcomed };
    });
    try { window.sessionStorage.setItem(storageKey, JSON.stringify(out)); } catch (error) {}
  }
  function saveTriggerConfig() {
    try { window.localStorage.setItem(triggerConfigKey, JSON.stringify(triggerConfig)); } catch (error) {}
  }

  function makeNode(card, index) {
    var id = card.dataset.sessionId || '';
    var bounds = layer.getBoundingClientRect();
    var width = Math.max(210, card.offsetWidth || 230);
    var height = Math.max(72, card.offsetHeight || 72);
    var start = (saved.customers && saved.customers[id]) || saved[id] || {};
    return {
      id: id,
      card: card,
      index: index,
      width: width,
      height: height,
      x: Number.isFinite(start.x) ? start.x : rand(18, Math.max(22, bounds.width - width - 18)),
      y: Number.isFinite(start.y) ? start.y : rand(18, Math.max(22, bounds.height - height - 18)),
      vx: Number.isFinite(start.vx) ? start.vx : rand(-0.085, 0.085),
      vy: Number.isFinite(start.vy) ? start.vy : rand(-0.07, 0.07),
      welcomed: Boolean(start.welcomed),
      welcomeAt: now() + rand(4200, 8000),
      nextTurn: now() + rand(900, 3000),
      chatUntil: 0,
      behaviorBias: rand(0, 1)
    };
  }

  function makeMerchant() {
    if (!merchantCard) return null;
    var start = saved.merchant || {};
    return {
      id: 'merchant',
      card: merchantCard,
      width: Math.max(240, merchantCard.offsetWidth || 260),
      height: Math.max(74, merchantCard.offsetHeight || 74),
      x: Number.isFinite(start.x) ? start.x : 0,
      y: Number.isFinite(start.y) ? start.y : 0,
      vx: Number.isFinite(start.vx) ? start.vx : 0.045,
      vy: Number.isFinite(start.vy) ? start.vy : 0.032,
      targetId: '',
      nextTargetAt: now() + 2000,
      chatUntil: 0
    };
  }

  function ensureTriggerButton() {
    if (triggerButton && triggerButton.isConnected) return triggerButton;
    triggerButton = document.createElement('button');
    triggerButton.type = 'button';
    triggerButton.className = 'mg-canvas-trigger-add-btn';
    triggerButton.innerHTML = '<span>+</span> Trigger';
    triggerButton.setAttribute('data-canvas-add-trigger', '');
    map.appendChild(triggerButton);
    triggerButton.addEventListener('click', function () {
      var bounds = layer.getBoundingClientRect();
      if (!triggerConfig.exists) {
        triggerConfig.exists = true;
        triggerConfig.width = Math.min(300, Math.max(180, bounds.width * 0.3));
        triggerConfig.height = 130;
        triggerConfig.x = clamp(bounds.width - triggerConfig.width - 24, 8, Math.max(8, bounds.width - triggerConfig.width - 8));
        triggerConfig.y = clamp(bounds.height - triggerConfig.height - 24, 8, Math.max(8, bounds.height - triggerConfig.height - 8));
      }
      saveTriggerConfig();
      ensureTriggerZone();
      applyTriggerConfig();
      triggerZone.hidden = false;
      triggerZone.classList.add('is-editing');
      window.setTimeout(function () { if (triggerZone) triggerZone.classList.remove('is-editing'); }, 1800);
    });
    return triggerButton;
  }

  function selectedCampaignId() {
    var select = triggerZone ? triggerZone.querySelector('[data-canvas-trigger-campaign]') : null;
    return select ? String(select.value || '') : String(triggerConfig.campaign_id || '');
  }

  function renderTriggerCampaignOptions() {
    if (!triggerZone) return;
    var select = triggerZone.querySelector('[data-canvas-trigger-campaign]');
    var status = triggerZone.querySelector('[data-canvas-trigger-status]');
    if (!select) return;
    if (!triggerCampaignLoaded) {
      select.innerHTML = '<option value="">Loading campaigns...</option>';
      select.disabled = true;
      if (status) status.textContent = 'Loading active merchant campaigns.';
      return;
    }
    if (!triggerCampaigns.length) {
      select.innerHTML = '<option value="">No active campaigns</option>';
      select.disabled = true;
      triggerConfig.campaign_id = '';
      saveTriggerConfig();
      if (status) status.textContent = 'Create an active campaign to assign this trigger.';
      return;
    }
    var current = String(triggerConfig.campaign_id || '');
    var found = triggerCampaigns.some(function (campaign) { return String(campaign.id || '') === current; });
    if (!found) current = String(triggerCampaigns[0].id || '');
    triggerConfig.campaign_id = current;
    saveTriggerConfig();
    select.disabled = false;
    select.innerHTML = triggerCampaigns.map(function (campaign) {
      var label = String(campaign.title || 'Campaign') + (campaign.reward_template_title ? ' · ' + String(campaign.reward_template_title) : '');
      return '<option value="' + escapeHtml(campaign.id || '') + '"' + (String(campaign.id || '') === current ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
    }).join('');
    if (status) status.textContent = 'Drag/resize this square. Crossing it fires the selected campaign.';
  }

  async function loadTriggerCampaigns() {
    if (!MG.get) {
      triggerCampaignLoaded = true;
      renderTriggerCampaignOptions();
      return;
    }
    try {
      var data = payload(await MG.get('/api/merchant-canvas/reward-options.php')) || {};
      triggerCampaigns = Array.isArray(data.campaigns) ? data.campaigns.filter(function (campaign) { return campaign && campaign.available !== false; }) : [];
    } catch (error) {
      triggerCampaigns = [];
    }
    triggerCampaignLoaded = true;
    renderTriggerCampaignOptions();
  }

  function applyTriggerConfig() {
    if (!triggerZone) return;
    var bounds = layer.getBoundingClientRect();
    triggerConfig.width = clamp(Number(triggerConfig.width || 280), 140, Math.max(160, bounds.width - 16));
    triggerConfig.height = clamp(Number(triggerConfig.height || 130), 92, Math.max(110, bounds.height - 16));
    triggerConfig.x = clamp(Number(triggerConfig.x || 8), 8, Math.max(8, bounds.width - triggerConfig.width - 8));
    triggerConfig.y = clamp(Number(triggerConfig.y || 8), 8, Math.max(8, bounds.height - triggerConfig.height - 8));
    triggerZone.style.left = Math.round(triggerConfig.x) + 'px';
    triggerZone.style.top = Math.round(triggerConfig.y) + 'px';
    triggerZone.style.width = Math.round(triggerConfig.width) + 'px';
    triggerZone.style.height = Math.round(triggerConfig.height) + 'px';
    triggerZone.hidden = !triggerConfig.exists;
  }

  function ensureTriggerZone() {
    if (triggerZone && triggerZone.isConnected) return triggerZone;
    triggerZone = document.createElement('div');
    triggerZone.className = 'mg-canvas-trigger-zone';
    triggerZone.setAttribute('data-canvas-trigger-zone', 'in_out_box_zone');
    triggerZone.innerHTML = '<span class="mg-canvas-trigger-zone-icon">IO</span><span class="mg-canvas-trigger-zone-copy"><strong>IN/OUT Box Trigger</strong><span>Transparent campaign trigger square.</span><label>Assigned campaign<select data-canvas-trigger-campaign><option value="">Loading campaigns...</option></select></label><em data-canvas-trigger-status>Drag or resize anywhere on the canvas.</em></span><span class="mg-canvas-trigger-drag-hint">Drag zone</span><span class="mg-canvas-trigger-resize" data-canvas-trigger-resize aria-hidden="true"></span>';
    layer.appendChild(triggerZone);
    triggerZone.addEventListener('change', function (event) {
      var select = event.target.closest('[data-canvas-trigger-campaign]');
      if (!select) return;
      triggerConfig.campaign_id = String(select.value || '');
      saveTriggerConfig();
      var status = triggerZone.querySelector('[data-canvas-trigger-status]');
      if (status) status.textContent = triggerConfig.campaign_id ? 'Trigger assigned. Crossing this square fires this campaign.' : 'Select a campaign for this trigger.';
    });
    triggerZone.addEventListener('pointerdown', startTriggerPointer);
    renderTriggerCampaignOptions();
    applyTriggerConfig();
    return triggerZone;
  }

  function startTriggerPointer(event) {
    if (!triggerZone || event.button !== 0) return;
    if (event.target.closest('select,label')) return;
    var resizing = !!event.target.closest('[data-canvas-trigger-resize]');
    var bounds = layer.getBoundingClientRect();
    dragState = {
      mode: resizing ? 'resize' : 'move',
      startX: event.clientX,
      startY: event.clientY,
      x: Number(triggerConfig.x || 0),
      y: Number(triggerConfig.y || 0),
      width: Number(triggerConfig.width || 280),
      height: Number(triggerConfig.height || 130),
      boundsWidth: bounds.width,
      boundsHeight: bounds.height
    };
    triggerZone.setPointerCapture(event.pointerId);
    triggerZone.classList.add('is-editing');
    event.preventDefault();
  }

  function moveTriggerPointer(event) {
    if (!dragState || !triggerZone) return;
    var dx = event.clientX - dragState.startX;
    var dy = event.clientY - dragState.startY;
    if (dragState.mode === 'resize') {
      triggerConfig.width = clamp(dragState.width + dx, 140, Math.max(160, dragState.boundsWidth - dragState.x - 8));
      triggerConfig.height = clamp(dragState.height + dy, 92, Math.max(110, dragState.boundsHeight - dragState.y - 8));
    } else {
      triggerConfig.x = clamp(dragState.x + dx, 8, Math.max(8, dragState.boundsWidth - triggerConfig.width - 8));
      triggerConfig.y = clamp(dragState.y + dy, 8, Math.max(8, dragState.boundsHeight - triggerConfig.height - 8));
    }
    applyTriggerConfig();
  }

  function endTriggerPointer() {
    if (!dragState) return;
    dragState = null;
    if (triggerZone) triggerZone.classList.remove('is-editing');
    saveTriggerConfig();
  }

  function syncNodes() {
    map.classList.add('is-motion-enabled');
    ensureTriggerButton();
    if (triggerConfig.exists) ensureTriggerZone();
    if (triggerZone) applyTriggerConfig();
    if (merchantCard && !merchant) merchant = makeMerchant();
    if (merchant && merchant.card) {
      merchant.card.classList.add('is-moving');
      merchant.width = Math.max(240, merchant.card.offsetWidth || merchant.width || 260);
      merchant.height = Math.max(74, merchant.card.offsetHeight || merchant.height || 74);
      merchant.card.style.transform = 'translate3d(' + Math.round(merchant.x) + 'px,' + Math.round(merchant.y) + 'px,0)';
    }
    var cards = Array.from(layer.querySelectorAll('[data-session-id]'));
    var seen = new Set();
    cards.forEach(function (card, index) {
      var id = card.dataset.sessionId || '';
      if (!id) return;
      seen.add(id);
      if (!nodes.has(id)) nodes.set(id, makeNode(card, index));
      else {
        var node = nodes.get(id);
        node.card = card;
        node.index = index;
        node.width = Math.max(210, card.offsetWidth || node.width || 230);
        node.height = Math.max(72, card.offsetHeight || node.height || 72);
      }
      card.classList.add('is-moving');
      card.style.transform = 'translate3d(' + Math.round(nodes.get(id).x) + 'px,' + Math.round(nodes.get(id).y) + 'px,0)';
    });
    Array.from(nodes.keys()).forEach(function (id) { if (!seen.has(id)) nodes.delete(id); });
    if (!animationFrame && (nodes.size || merchant)) animationFrame = window.requestAnimationFrame(tick);
  }

  function rectanglesOverlap(a, b, pad) {
    pad = pad || 0;
    return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y;
  }

  function merchantRect() {
    if (!merchant || !merchant.card) return null;
    var m = merchant.card.getBoundingClientRect();
    var l = layer.getBoundingClientRect();
    return { x: m.left - l.left, y: m.top - l.top, width: m.width, height: m.height };
  }

  function zoneRect() {
    if (!triggerConfig.exists) return null;
    var zone = ensureTriggerZone();
    if (!zone || zone.hidden) return null;
    return { x: Number(triggerConfig.x || 0), y: Number(triggerConfig.y || 0), width: Number(triggerConfig.width || 280), height: Number(triggerConfig.height || 130), el: zone };
  }

  function bubble(node, text, label) {
    if (!node || !node.card) return;
    var b = document.createElement('div');
    b.className = 'mg-canvas-chat-bubble';
    b.innerHTML = escapeHtml(text) + '<small>' + escapeHtml(label || 'Actual message sent') + '</small>';
    var rect = node.id === 'merchant' ? merchantRect() : node;
    if (!rect) return;
    b.style.left = Math.round((rect.x || 0) + (rect.width || node.width) / 2) + 'px';
    b.style.top = Math.round((rect.y || node.y || 0) + 4) + 'px';
    layer.appendChild(b);
    window.setTimeout(function () { b.remove(); }, 4300);
  }

  function line(a, b) {
    var ar = a.id === 'merchant' ? merchantRect() : a;
    var br = b.id === 'merchant' ? merchantRect() : b;
    if (!ar || !br) return;
    var x1 = ar.x + ar.width / 2, y1 = ar.y + ar.height / 2;
    var x2 = br.x + br.width / 2, y2 = br.y + br.height / 2;
    var dx = x2 - x1, dy = y2 - y1;
    var len = Math.sqrt(dx * dx + dy * dy);
    var el = document.createElement('span');
    el.className = 'mg-canvas-motion-line';
    el.style.left = Math.round(x1) + 'px';
    el.style.top = Math.round(y1) + 'px';
    el.style.width = Math.round(len) + 'px';
    el.style.transform = 'rotate(' + Math.atan2(dy, dx) + 'rad)';
    layer.appendChild(el);
    window.setTimeout(function () { el.remove(); }, 1800);
  }

  async function sendAutoChat(node, context, peerId) {
    var key = context + ':' + node.id + ':' + (peerId || '');
    if (now() - (lastTalk.get(key) || 0) < 165000) return;
    lastTalk.set(key, now());
    node.chatUntil = now() + 5200;
    if (merchant) merchant.chatUntil = now() + 5200;
    node.card.classList.add('is-chatting');
    if (merchant && merchant.card) merchant.card.classList.add('is-chatting');
    bubble(node, context === 'avatar_overlap' ? 'The avatars stopped to chat.' : 'Merchant is approaching to introduce the store.', 'Starting chat');
    if (merchant) line(merchant, node);
    try {
      var data = payload(await MG.post('/api/merchant-canvas/auto-chat.php', { session_id: node.id, context: context, peer_session_id: peerId || '' }));
      if (data && data.sent === false) return;
      bubble(node, context === 'avatar_overlap' ? 'Merchant auto-chat delivered.' : 'Merchant welcome sent.', 'Message delivered');
    } catch (error) {
      lastTalk.set(key, 0);
    } finally {
      window.setTimeout(function () {
        if (node.card) node.card.classList.remove('is-chatting');
        if (merchant && merchant.card) merchant.card.classList.remove('is-chatting');
      }, 5200);
    }
  }

  async function fireCampaignTrigger(node) {
    var campaignId = selectedCampaignId();
    var key = 'in_out_box_zone:' + node.id + ':' + (campaignId || 'none');
    if (now() - (lastTrigger.get(key) || 0) < 240000) return;
    lastTrigger.set(key, now());
    var zone = ensureTriggerZone();
    if (zone) zone.classList.add('is-hot');
    node.card.classList.add('is-triggered');
    bubble(node, campaignId ? 'Assigned campaign trigger activated.' : 'Trigger zone activated without assigned campaign.', 'IN/OUT Box');
    try {
      var data = payload(await MG.post('/api/merchant-canvas/campaign-trigger.php', { session_id: node.id, trigger_key: 'in_out_box_zone', trigger_label: 'IN/OUT Box Campaign Trigger', campaign_id: campaignId }));
      if (data && data.reward_sent) bubble(node, 'Reward sent from assigned campaign.', 'Campaign fired');
      else if (data && data.message_sent) bubble(node, 'Campaign message sent to IN/OUT Box.', 'Campaign fired');
    } catch (error) {
      lastTrigger.set(key, 0);
    } finally {
      window.setTimeout(function () {
        if (zone) zone.classList.remove('is-hot');
        if (node.card) node.card.classList.remove('is-triggered');
      }, 5200);
    }
  }

  function chooseMerchantTarget(timestamp) {
    if (!merchant || timestamp < merchant.nextTargetAt) return;
    var list = Array.from(nodes.values());
    if (!list.length) {
      merchant.targetId = '';
      merchant.nextTargetAt = timestamp + rand(2500, 5200);
      return;
    }
    list.sort(function (a, b) {
      var scoreA = (a.welcomed ? 0.35 : 1) + a.behaviorBias + rand(0, 0.25);
      var scoreB = (b.welcomed ? 0.35 : 1) + b.behaviorBias + rand(0, 0.25);
      return scoreB - scoreA;
    });
    merchant.targetId = list[0].id;
    merchant.nextTargetAt = timestamp + rand(3600, 7200);
  }

  function updateMerchant(delta, timestamp) {
    if (!merchant || !merchant.card) return;
    var mapBounds = map.getBoundingClientRect();
    var baseLeft = 26;
    var baseTop = 28;
    merchant.card.classList.toggle('is-chatting', merchant.chatUntil > now());
    if (merchant.chatUntil > now()) {
      merchant.card.style.transform = 'translate3d(' + Math.round(merchant.x) + 'px,' + Math.round(merchant.y) + 'px,0)';
      return;
    }
    chooseMerchantTarget(timestamp);
    var target = merchant.targetId ? nodes.get(merchant.targetId) : null;
    if (target) {
      var mr = merchantRect() || { x: baseLeft + merchant.x, y: baseTop + merchant.y, width: merchant.width, height: merchant.height };
      var dx = (target.x + target.width / 2) - (mr.x + mr.width / 2);
      var dy = (target.y + target.height / 2) - (mr.y + mr.height / 2);
      var len = Math.max(1, Math.sqrt(dx * dx + dy * dy));
      merchant.vx = (dx / len) * 0.12;
      merchant.vy = (dy / len) * 0.095;
      merchant.card.classList.add('is-approaching');
      if (len < 175) {
        target.chatUntil = now() + 5200;
        merchant.chatUntil = now() + 5200;
        sendAutoChat(target, 'merchant_proximity', '');
      }
    } else {
      merchant.card.classList.remove('is-approaching');
      if (timestamp > merchant.nextTargetAt - 500) {
        merchant.vx += rand(-0.025, 0.025);
        merchant.vy += rand(-0.02, 0.02);
      }
      merchant.vx = clamp(merchant.vx, -0.095, 0.095);
      merchant.vy = clamp(merchant.vy, -0.075, 0.075);
    }
    merchant.x += merchant.vx * delta;
    merchant.y += merchant.vy * delta;
    var maxX = Math.max(0, mapBounds.width - merchant.width - baseLeft - 20);
    var maxY = Math.max(0, mapBounds.height - merchant.height - baseTop - 20);
    if (merchant.x <= 0 || merchant.x >= maxX) merchant.vx *= -1;
    if (merchant.y <= 0 || merchant.y >= maxY) merchant.vy *= -1;
    merchant.x = clamp(merchant.x, 0, maxX);
    merchant.y = clamp(merchant.y, 0, maxY);
    merchant.card.style.transform = 'translate3d(' + Math.round(merchant.x) + 'px,' + Math.round(merchant.y) + 'px,0)';
  }

  function checkInteractions() {
    var list = Array.from(nodes.values());
    var m = merchantRect();
    var z = zoneRect();
    list.forEach(function (node) {
      node.card.classList.remove('is-nearby');
      if (!node.welcomed && now() >= node.welcomeAt) {
        node.welcomed = true;
        if (merchant) merchant.targetId = node.id;
      }
      if (m && rectanglesOverlap(node, m, 90)) {
        node.card.classList.add('is-nearby');
        sendAutoChat(node, 'merchant_proximity', '');
      }
      if (z && rectanglesOverlap(node, z, -8)) {
        fireCampaignTrigger(node);
      }
    });
    for (var i = 0; i < list.length; i++) {
      for (var j = i + 1; j < list.length; j++) {
        if (rectanglesOverlap(list[i], list[j], 8)) {
          list[i].card.classList.add('is-nearby');
          list[j].card.classList.add('is-nearby');
          list[i].chatUntil = now() + 4800;
          list[j].chatUntil = now() + 4800;
          line(list[i], list[j]);
          sendAutoChat(list[i], 'avatar_overlap', list[j].id);
          sendAutoChat(list[j], 'avatar_overlap', list[i].id);
        }
      }
    }
  }

  function tick(timestamp) {
    if (!lastTick) lastTick = timestamp;
    var delta = Math.min(40, timestamp - lastTick);
    lastTick = timestamp;
    var bounds = layer.getBoundingClientRect();
    nodes.forEach(function (node) {
      node.card.classList.toggle('is-chatting', node.chatUntil > now());
      if (node.chatUntil <= now()) {
        if (timestamp > node.nextTurn) {
          node.vx += rand(-0.05, 0.05);
          node.vy += rand(-0.04, 0.04);
          node.vx = clamp(node.vx, -0.145, 0.145);
          node.vy = clamp(node.vy, -0.115, 0.115);
          node.nextTurn = timestamp + rand(850, 2800);
        }
        node.x += node.vx * delta;
        node.y += node.vy * delta;
      }
      var maxX = Math.max(8, bounds.width - node.width - 8);
      var maxY = Math.max(8, bounds.height - node.height - 8);
      if (node.x <= 8 || node.x >= maxX) node.vx *= -1;
      if (node.y <= 8 || node.y >= maxY) node.vy *= -1;
      node.x = clamp(node.x, 8, maxX);
      node.y = clamp(node.y, 8, maxY);
      node.card.style.transform = 'translate3d(' + Math.round(node.x) + 'px,' + Math.round(node.y) + 'px,0)';
    });
    updateMerchant(delta, timestamp);
    if (Math.floor(timestamp / 750) !== Math.floor((timestamp - delta) / 750)) checkInteractions();
    animationFrame = (nodes.size || merchant) ? window.requestAnimationFrame(tick) : null;
  }

  var observer = new MutationObserver(syncNodes);
  observer.observe(layer, { childList: true, subtree: false });
  document.addEventListener('pointermove', moveTriggerPointer);
  document.addEventListener('pointerup', endTriggerPointer);
  window.addEventListener('resize', syncNodes);
  window.addEventListener('beforeunload', saveState);
  ensureTriggerButton();
  if (triggerConfig.exists) ensureTriggerZone();
  loadTriggerCampaigns();
  syncNodes();
})(window, document);
