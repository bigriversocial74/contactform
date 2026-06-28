window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG.post) return;

  var map = root.querySelector('[data-canvas-map]');
  var layer = root.querySelector('[data-canvas-customers]');
  if (!map || !layer) return;

  var nodes = new Map();
  var lastTalk = new Map();
  var animationFrame = null;
  var lastTick = 0;
  var storageKey = 'mgCanvasMotion:v1';
  var saved = {};

  try { saved = JSON.parse(window.sessionStorage.getItem(storageKey) || '{}') || {}; }
  catch (error) { saved = {}; }

  function payload(response) { return response && response.data ? response.data : response; }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function now() { return Date.now(); }
  function rand(min, max) { return min + Math.random() * (max - min); }
  function safeId(value) { return String(value || '').replace(/[^a-zA-Z0-9_-]/g, ''); }
  function saveState() {
    var out = {};
    nodes.forEach(function (node, id) {
      out[id] = { x: node.x, y: node.y, vx: node.vx, vy: node.vy };
    });
    try { window.sessionStorage.setItem(storageKey, JSON.stringify(out)); } catch (error) {}
  }

  function makeNode(card, index) {
    var id = card.dataset.sessionId || '';
    var bounds = layer.getBoundingClientRect();
    var width = Math.max(210, card.offsetWidth || 230);
    var height = Math.max(72, card.offsetHeight || 72);
    var start = saved[id] || {};
    return {
      id: id,
      card: card,
      index: index,
      width: width,
      height: height,
      x: Number.isFinite(start.x) ? start.x : rand(18, Math.max(22, bounds.width - width - 18)),
      y: Number.isFinite(start.y) ? start.y : rand(18, Math.max(22, bounds.height - height - 18)),
      vx: Number.isFinite(start.vx) ? start.vx : rand(-0.045, 0.045),
      vy: Number.isFinite(start.vy) ? start.vy : rand(-0.035, 0.035),
      nextTurn: now() + rand(1200, 4200)
    };
  }

  function syncNodes() {
    map.classList.add('is-motion-enabled');
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
    if (!animationFrame && nodes.size) animationFrame = window.requestAnimationFrame(tick);
  }

  function rectanglesOverlap(a, b, pad) {
    pad = pad || 0;
    return a.x < b.x + b.width + pad && a.x + a.width + pad > b.x && a.y < b.y + b.height + pad && a.y + a.height + pad > b.y;
  }

  function merchantRect() {
    var merchant = root.querySelector('.mg-canvas-merchant-node');
    if (!merchant) return null;
    var m = merchant.getBoundingClientRect();
    var l = layer.getBoundingClientRect();
    return { x: m.left - l.left, y: m.top - l.top, width: m.width, height: m.height };
  }

  function bubble(node, text, label) {
    if (!node || !node.card) return;
    var b = document.createElement('div');
    b.className = 'mg-canvas-chat-bubble';
    b.innerHTML = text + '<small>' + (label || 'Actual message sent') + '</small>';
    b.style.left = Math.round(node.x + node.width / 2) + 'px';
    b.style.top = Math.round(node.y + 4) + 'px';
    layer.appendChild(b);
    window.setTimeout(function () { b.remove(); }, 4300);
  }

  function line(a, b) {
    var x1 = a.x + a.width / 2, y1 = a.y + a.height / 2;
    var x2 = b.x + b.width / 2, y2 = b.y + b.height / 2;
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
    node.card.classList.add('is-chatting');
    bubble(node, context === 'avatar_overlap' ? 'Looks like another customer is browsing nearby.' : 'Welcome in — merchant agent is here.', 'Sending message');
    try {
      var data = payload(await MG.post('/api/merchant-canvas/auto-chat.php', { session_id: node.id, context: context, peer_session_id: peerId || '' }));
      if (data && data.sent === false) return;
      bubble(node, context === 'avatar_overlap' ? 'Merchant auto-chat delivered.' : 'Merchant welcome sent.', 'Message delivered');
    } catch (error) {
      lastTalk.set(key, 0);
    } finally {
      window.setTimeout(function () { if (node.card) node.card.classList.remove('is-chatting'); }, 3800);
    }
  }

  function checkInteractions() {
    var list = Array.from(nodes.values());
    var m = merchantRect();
    list.forEach(function (node) {
      node.card.classList.remove('is-nearby');
      if (m && rectanglesOverlap(node, m, 22)) {
        node.card.classList.add('is-nearby');
        sendAutoChat(node, 'merchant_proximity', '');
      }
    });
    for (var i = 0; i < list.length; i++) {
      for (var j = i + 1; j < list.length; j++) {
        if (rectanglesOverlap(list[i], list[j], 8)) {
          list[i].card.classList.add('is-nearby');
          list[j].card.classList.add('is-nearby');
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
      if (timestamp > node.nextTurn) {
        node.vx += rand(-0.035, 0.035);
        node.vy += rand(-0.03, 0.03);
        node.vx = clamp(node.vx, -0.085, 0.085);
        node.vy = clamp(node.vy, -0.065, 0.065);
        node.nextTurn = timestamp + rand(1800, 5200);
      }
      node.x += node.vx * delta;
      node.y += node.vy * delta;
      var maxX = Math.max(8, bounds.width - node.width - 8);
      var maxY = Math.max(8, bounds.height - node.height - 8);
      if (node.x <= 8 || node.x >= maxX) node.vx *= -1;
      if (node.y <= 8 || node.y >= maxY) node.vy *= -1;
      node.x = clamp(node.x, 8, maxX);
      node.y = clamp(node.y, 8, maxY);
      node.card.style.transform = 'translate3d(' + Math.round(node.x) + 'px,' + Math.round(node.y) + 'px,0)';
    });
    if (Math.floor(timestamp / 900) !== Math.floor((timestamp - delta) / 900)) checkInteractions();
    animationFrame = nodes.size ? window.requestAnimationFrame(tick) : null;
  }

  var observer = new MutationObserver(syncNodes);
  observer.observe(layer, { childList: true, subtree: false });
  window.addEventListener('resize', syncNodes);
  window.addEventListener('beforeunload', saveState);
  syncNodes();
})(window, document);
