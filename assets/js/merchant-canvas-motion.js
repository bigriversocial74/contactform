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
  var animationFrame = null;
  var lastTick = 0;
  var storageKey = 'mgCanvasMotion:v2';
  var saved = {};

  try { saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}') || {}; }
  catch (error) { saved = {}; }

  function payload(response) { return response && response.data ? response.data : response; }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function now() { return Date.now(); }
  function rand(min, max) { return min + Math.random() * (max - min); }
  function distance(a, b) {
    var ax = a.x + a.width / 2, ay = a.y + a.height / 2;
    var bx = b.x + b.width / 2, by = b.y + b.height / 2;
    var dx = bx - ax, dy = by - ay;
    return Math.sqrt(dx * dx + dy * dy);
  }
  function saveState() {
    var out = { customers: {}, merchant: merchant ? { x: merchant.x, y: merchant.y, vx: merchant.vx, vy: merchant.vy } : null };
    nodes.forEach(function (node, id) {
      out.customers[id] = { x: node.x, y: node.y, vx: node.vx, vy: node.vy, welcomed: node.welcomed };
    });
    try { window.sessionStorage.setItem(storageKey, JSON.stringify(out)); } catch (error) {}
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
    var width = Math.max(240, merchantCard.offsetWidth || 260);
    var height = Math.max(74, merchantCard.offsetHeight || 74);
    return {
      id: 'merchant',
      card: merchantCard,
      width: width,
      height: height,
      x: Number.isFinite(start.x) ? start.x : 0,
      y: Number.isFinite(start.y) ? start.y : 0,
      vx: Number.isFinite(start.vx) ? start.vx : 0.045,
      vy: Number.isFinite(start.vy) ? start.vy : 0.032,
      targetId: '',
      nextTargetAt: now() + 2000,
      chatUntil: 0
    };
  }

  function ensureTriggerZone() {
    if (triggerZone && triggerZone.isConnected) return triggerZone;
    triggerZone = document.createElement('div');
    triggerZone.className = 'mg-canvas-trigger-zone';
    triggerZone.setAttribute('data-canvas-trigger-zone', 'in_out_box_zone');
    triggerZone.innerHTML = '<span class="mg-canvas-trigger-zone-icon">IO</span><span class="mg-canvas-trigger-zone-copy"><strong>IN/OUT Box Trigger</strong><span>Customer crossing this space can trigger a campaign, message, or reward.</span></span>';
    layer.appendChild(triggerZone);
    return triggerZone;
  }

  function syncNodes() {
    map.classList.add('is-motion-enabled');
    ensureTriggerZone();
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
    var zone = ensureTriggerZone();
    if (!zone) return null;
    var z = zone.getBoundingClientRect();
    var l = layer.getBoundingClientRect();
    return { x: z.left - l.left, y: z.top - l.top, width: z.width, height: z.height, el: zone };
  }

  function bubble(node, text, label) {
    if (!node || !node.card) return;
    var b = document.createElement('div');
    b.className = 'mg-canvas-chat-bubble';
    b.innerHTML = text + '<small>' + (label || 'Actual message sent') + '</small>';
    var rect = node.id === 'merchant' ? merchantRect() : node;
    b.style.left = Math.round((rect.x || 0) + (rect.width || node.width) / 2) + 'px';
    b.style.top = Math.round((rect.y || node.y) + 4) + 'px';
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
    var key = 'in_out_box_zone:' + node.id;
    if (now() - (lastTrigger.get(key) || 0) < 240000) return;
    lastTrigger.set(key, now());
    var zone = ensureTriggerZone();
    if (zone) zone.classList.add('is-hot');
    node.card.classList.add('is-triggered');
    bubble(node, 'Campaign trigger zone activated.', 'IN/OUT Box');
    try {
      var data = payload(await MG.post('/api/merchant-canvas/campaign-trigger.php', { session_id: node.id, trigger_key: 'in_out_box_zone', trigger_label: 'IN/OUT Box Campaign Trigger' }));
      if (data && data.reward_sent) bubble(node, 'Reward sent from trigger zone.', 'Campaign fired');
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
  window.addEventListener('resize', syncNodes);
  window.addEventListener('beforeunload', saveState);
  syncNodes();
})(window, document);
