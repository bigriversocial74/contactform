window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-world-canvas]');
  if (!root || !MG.get) return;

  var activeFilter = 'all';
  var selectedNodeId = '';
  var pollTimer = null;
  var animationFrame = null;
  var nodeStates = new Map();
  var latestNodes = [];
  var attractionModel = { enabled: true, saved_lat_long_weight: 0.72, same_location_weight: 0.46, same_conversation_weight: 0.38, shared_affinity_weight: 0.28, repel_distance: 11 };
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var drawer = root.querySelector('[data-world-drawer]');

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function payload(response) { return response && response.data ? response.data : response; }
  function clear(node) { if (node) node.replaceChildren(); }
  function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function timeLabel(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).indexOf('T') === -1 ? 'Z' : ''));
    if (Number.isNaN(parsed.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(parsed);
  }
  function initials(value) {
    return String(value || 'MG').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part[0]; }).join('').toUpperCase() || 'MG';
  }

  function portalDrawer() {
    if (!drawer || !document.body) return null;
    if (drawer.parentElement !== document.body) document.body.appendChild(drawer);
    drawer.setAttribute('data-world-drawer-portal', 'body');
    return drawer;
  }
  function openDrawer() { var activeDrawer = portalDrawer(); if (activeDrawer) { activeDrawer.classList.add('is-open'); activeDrawer.setAttribute('aria-hidden', 'false'); } }
  function closeDrawer() { var activeDrawer = portalDrawer(); if (activeDrawer) { activeDrawer.classList.remove('is-open'); activeDrawer.setAttribute('aria-hidden', 'true'); } }

  function setLiveStatus(message, type) {
    var pill = qs('[data-world-live-pill]');
    if (!pill) return;
    pill.textContent = message;
    pill.classList.toggle('is-live', type === 'live');
    pill.classList.toggle('is-warn', type === 'warn');
    pill.classList.toggle('is-error', type === 'error');
  }
  function setState(message) { var state = qs('[data-world-state]'); if (state) state.textContent = message; }
  function setStats(summary) {
    summary = summary || {};
    Object.keys(summary).forEach(function (key) {
      qsa('[data-world-stat="' + key + '"]').forEach(function (node) { node.textContent = number(summary[key]); });
    });
  }

  function nodeIcon(node) {
    if (node.avatar_url) return '<span class="mg-world-node-icon"><img src="' + escapeHtml(node.avatar_url) + '" alt=""></span>';
    var label = node.type === 'avatar' ? 'AV' : node.type === 'campaign' ? 'CA' : node.type === 'reward' ? 'RW' : node.type === 'claim' ? 'CL' : initials(node.title);
    return '<span class="mg-world-node-icon">' + escapeHtml(label) + '</span>';
  }

  function renderNode(node) {
    var type = node.type || 'node';
    var tone = node.owned ? 'owned' : (node.tone || 'soft');
    var hidden = activeFilter !== 'all' && activeFilter !== type;
    var classes = 'mg-world-node is-' + escapeHtml(type) + ' is-' + escapeHtml(tone) + (node.has_geo ? ' is-geo' : '') + (node.geo_locked ? ' is-geo-locked' : '') + (node.id === selectedNodeId ? ' is-active' : '');
    return '<button class="' + classes + '" type="button" data-world-node data-world-node-id="' + escapeHtml(node.id || '') + '" data-world-type="' + escapeHtml(type) + '" data-world-detail-id="' + escapeHtml(node.detail_id || '') + '" data-world-location-key="' + escapeHtml(node.location_key || '') + '" data-world-conversation-key="' + escapeHtml(node.conversation_key || '') + '" data-world-affinity="' + escapeHtml((node.affinity_tags || []).join(',')) + '" data-world-target-x="' + escapeHtml(node.x || 50) + '" data-world-target-y="' + escapeHtml(node.y || 50) + '" data-world-geo-locked="' + (node.geo_locked ? 'true' : 'false') + '" style="left:' + escapeHtml(node.x || 50) + '%;top:' + escapeHtml(node.y || 50) + '%"' + (hidden ? ' hidden' : '') + '>' +
      '<span class="mg-world-node-status" aria-hidden="true"></span>' +
      '<span class="mg-world-node-head">' + nodeIcon(node) + '<span class="mg-world-node-copy"><strong>' + escapeHtml(node.title || 'World signal') + '</strong><span>' + escapeHtml(node.subtitle || '') + '</span></span><span class="mg-world-node-value">' + escapeHtml(node.value || '') + '</span></span>' +
      '<small class="mg-world-node-meta">' + escapeHtml(node.meta || '') + '</small>' +
      '</button>';
  }

  function sharedTags(a, b) {
    if (!a.tags.length || !b.tags.length) return 0;
    var count = 0;
    a.tags.forEach(function (tag) { if (b.tags.indexOf(tag) !== -1) count += 1; });
    return count;
  }

  function syncNodeStates(nodes) {
    var seen = new Set();
    qsa('[data-world-node]').forEach(function (el) {
      var id = el.dataset.worldNodeId || '';
      if (!id) return;
      seen.add(id);
      var targetX = Number(el.dataset.worldTargetX || 50);
      var targetY = Number(el.dataset.worldTargetY || 50);
      var current = nodeStates.get(id) || { x: targetX, y: targetY, vx: 0, vy: 0 };
      current.el = el;
      current.id = id;
      current.type = el.dataset.worldType || 'node';
      current.location = el.dataset.worldLocationKey || '';
      current.conversation = el.dataset.worldConversationKey || '';
      current.tags = String(el.dataset.worldAffinity || '').split(',').filter(Boolean);
      current.targetX = targetX;
      current.targetY = targetY;
      current.geoLocked = el.dataset.worldGeoLocked === 'true';
      current.visible = !el.hidden;
      nodeStates.set(id, current);
    });
    Array.from(nodeStates.keys()).forEach(function (id) { if (!seen.has(id)) nodeStates.delete(id); });
    if (reduceMotion || !attractionModel.enabled) {
      nodeStates.forEach(function (state) {
        state.x = state.targetX;
        state.y = state.targetY;
        if (state.el) { state.el.style.left = state.x + '%'; state.el.style.top = state.y + '%'; }
      });
      renderFlows(nodes);
      return;
    }
    if (!animationFrame) animationFrame = window.requestAnimationFrame(tickPhysics);
  }

  function tickPhysics() {
    animationFrame = null;
    var states = Array.from(nodeStates.values()).filter(function (state) { return state.visible; });
    var repelDistance = Number(attractionModel.repel_distance || 11);
    states.forEach(function (state) {
      var anchorStrength = state.geoLocked ? 0.052 : 0.012;
      state.vx += (state.targetX - state.x) * anchorStrength;
      state.vy += (state.targetY - state.y) * anchorStrength;
    });
    for (var i = 0; i < states.length; i += 1) {
      for (var j = i + 1; j < states.length; j += 1) {
        var a = states[i];
        var b = states[j];
        var dx = b.x - a.x;
        var dy = b.y - a.y;
        var dist = Math.sqrt(dx * dx + dy * dy) || 0.01;
        var nx = dx / dist;
        var ny = dy / dist;
        var attraction = 0;
        if (a.conversation && a.conversation === b.conversation) attraction += 0.0048 * Number(attractionModel.same_conversation_weight || 0.38);
        if (a.location && a.location === b.location) attraction += 0.0037 * Number(attractionModel.same_location_weight || 0.46);
        attraction += Math.min(3, sharedTags(a, b)) * 0.0016 * Number(attractionModel.shared_affinity_weight || 0.28);
        if (attraction && dist > 5) {
          a.vx += nx * attraction;
          a.vy += ny * attraction;
          b.vx -= nx * attraction;
          b.vy -= ny * attraction;
        }
        if (dist < repelDistance) {
          var repel = (repelDistance - dist) * 0.0026;
          a.vx -= nx * repel;
          a.vy -= ny * repel;
          b.vx += nx * repel;
          b.vy += ny * repel;
        }
      }
    }
    states.forEach(function (state) {
      state.vx *= state.geoLocked ? 0.72 : 0.82;
      state.vy *= state.geoLocked ? 0.72 : 0.82;
      state.x = clamp(state.x + state.vx, 4, 96);
      state.y = clamp(state.y + state.vy, 5, 95);
      if (state.geoLocked) {
        state.x = clamp(state.x, state.targetX - 8, state.targetX + 8);
        state.y = clamp(state.y, state.targetY - 8, state.targetY + 8);
      }
      if (state.el) {
        state.el.style.left = state.x.toFixed(3) + '%';
        state.el.style.top = state.y.toFixed(3) + '%';
      }
    });
    renderFlows(latestNodes, true);
    animationFrame = window.requestAnimationFrame(tickPhysics);
  }

  function positionFor(node) {
    var state = nodeStates.get(node.id || '');
    return state ? { x: state.x, y: state.y } : { x: Number(node.x || 50), y: Number(node.y || 50) };
  }

  function renderFlows(nodes) {
    var svg = qs('[data-world-flows]');
    if (!svg) return;
    clear(svg);
    nodes = Array.isArray(nodes) ? nodes : [];
    if (nodes.length < 2) return;
    var anchors = nodes.filter(function (node) { return node.type === 'merchant' || node.type === 'avatar'; }).slice(0, 12);
    var movers = nodes.filter(function (node) { return node.type === 'avatar' || node.type === 'reward' || node.type === 'claim' || node.type === 'campaign'; }).slice(0, 20);
    movers.forEach(function (node, index) {
      var from = anchors.find(function (anchor) { return anchor.id !== node.id && anchor.location_key && anchor.location_key === node.location_key; }) || anchors[index % Math.max(1, anchors.length)] || nodes[index % nodes.length];
      if (!from || from.id === node.id) return;
      var start = positionFor(from);
      var end = positionFor(node);
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1', String(start.x));
      line.setAttribute('y1', String(start.y));
      line.setAttribute('x2', String(end.x));
      line.setAttribute('y2', String(end.y));
      if (node.type === 'avatar') line.classList.add('is-avatar');
      if (node.type === 'reward') line.classList.add('is-reward');
      if (node.type === 'claim') line.classList.add('is-claim');
      svg.appendChild(line);
    });
  }

  function renderEvents(events) {
    var list = qs('[data-world-events]');
    if (!list) return;
    clear(list);
    events = Array.isArray(events) ? events : [];
    if (!events.length) { list.innerHTML = '<p>No live network events yet.</p>'; return; }
    list.innerHTML = events.slice(0, 12).map(function (event) {
      var type = event.type || 'avatar';
      var icon = type === 'avatar' ? 'AV' : type === 'campaign' ? 'CA' : type === 'reward' ? 'RW' : type === 'claim' ? 'CL' : 'EV';
      return '<article class="mg-world-event is-' + escapeHtml(type) + '"><b>' + escapeHtml(icon) + '</b><span><strong>' + escapeHtml(event.label || event.title || 'World activity') + '</strong><span>' + escapeHtml(event.title || '') + (event.meta ? ' · ' + escapeHtml(event.meta) : '') + '</span></span><time>' + escapeHtml(timeLabel(event.created_at)) + '</time></article>';
    }).join('');
  }

  function applyFilter() {
    qsa('[data-world-filter]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.worldFilter === activeFilter); });
    qsa('[data-world-node]').forEach(function (node) { node.hidden = activeFilter !== 'all' && node.dataset.worldType !== activeFilter; });
    nodeStates.forEach(function (state) { state.visible = !state.el || !state.el.hidden; });
  }

  function renderWorld(data) {
    data = data || {};
    var summary = data.summary || {};
    latestNodes = Array.isArray(data.nodes) ? data.nodes : [];
    attractionModel = Object.assign(attractionModel, data.attraction_model || {});
    setStats(summary);
    setLiveStatus(latestNodes.length ? 'World Canvas live' : (summary.schema_ready ? 'Network ready' : 'Setup pending'), latestNodes.length ? 'live' : (summary.schema_ready ? 'warn' : 'error'));
    setState(summary.schema_ready ? 'Avatars use saved latitude/longitude when available, then attract toward similar avatars and same-location conversations.' : 'World Canvas is waiting on Store Canvas tables before live activity can appear.');
    var layer = qs('[data-world-nodes]');
    if (layer) {
      clear(layer);
      layer.insertAdjacentHTML('beforeend', latestNodes.map(renderNode).join(''));
    }
    var empty = qs('[data-world-empty]');
    if (empty) empty.classList.toggle('is-hidden', latestNodes.length > 0);
    renderEvents(data.events || []);
    applyFilter();
    syncNodeStates(latestNodes);
  }

  async function loadWorld() {
    try {
      var data = payload(await MG.get('/api/world-canvas/activity.php'));
      renderWorld(data || {});
    } catch (error) {
      setLiveStatus(error.message || 'Unable to load World Canvas', 'error');
      setState(error.message || 'Unable to load World Canvas activity.');
    }
  }

  function statMarkup(stats) {
    stats = Array.isArray(stats) ? stats : [];
    if (!stats.length) return '';
    return '<section class="mg-world-detail-grid">' + stats.map(function (stat) { return '<article class="mg-world-detail-stat"><span>' + escapeHtml(stat.label || 'Metric') + '</span><strong>' + escapeHtml(stat.value == null ? '—' : stat.value) + '</strong></article>'; }).join('') + '</section>';
  }
  function actionsMarkup(actions) {
    actions = Array.isArray(actions) ? actions.filter(function (item) { return item && item.href; }) : [];
    if (!actions.length) return '';
    return '<section class="mg-world-detail-actions">' + actions.map(function (action) { return '<a href="' + escapeHtml(action.href) + '">' + escapeHtml(action.label || 'Open') + '</a>'; }).join('') + '</section>';
  }
  function renderDetail(detail) {
    detail = detail || {};
    var body = drawer ? drawer.querySelector('[data-world-drawer-body]') : null;
    if (!body) return;
    var avatar = detail.avatar_url ? '<span class="mg-world-detail-avatar"><img src="' + escapeHtml(detail.avatar_url) + '" alt=""></span>' : '<span class="mg-world-detail-avatar">' + escapeHtml(initials(detail.title)) + '</span>';
    body.innerHTML = '<section class="mg-world-detail-hero">' + avatar + '<div><strong>' + escapeHtml(detail.title || 'World detail') + '</strong><span>' + escapeHtml(detail.subtitle || '') + '</span></div></section>' + statMarkup(detail.stats) + actionsMarkup(detail.actions) + (detail.note ? '<section class="mg-world-detail-note">' + escapeHtml(detail.note) + '</section>' : '');
  }

  async function loadDetail(type, detailId, nodeId) {
    selectedNodeId = nodeId || '';
    qsa('[data-world-node]').forEach(function (node) { node.classList.toggle('is-active', node.dataset.worldNodeId === selectedNodeId); });
    openDrawer();
    var title = drawer ? drawer.querySelector('[data-world-drawer-title]') : null;
    var subtitle = drawer ? drawer.querySelector('[data-world-drawer-subtitle]') : null;
    var kind = drawer ? drawer.querySelector('[data-world-drawer-type]') : null;
    var body = drawer ? drawer.querySelector('[data-world-drawer-body]') : null;
    if (title) title.textContent = 'Loading detail...';
    if (subtitle) subtitle.textContent = 'Pulling live World Canvas context.';
    if (kind) kind.textContent = String(type || 'world') + ' detail';
    if (body) body.innerHTML = '<div class="mg-world-drawer-empty"><strong>Loading node</strong><p>Reading the aggregate world layer.</p></div>';
    try {
      var data = payload(await MG.get('/api/world-canvas/detail.php?type=' + encodeURIComponent(type || '') + '&id=' + encodeURIComponent(detailId || '')));
      var detail = data.detail || data;
      if (title) title.textContent = detail.title || 'World detail';
      if (subtitle) subtitle.textContent = detail.subtitle || '';
      if (kind) kind.textContent = (detail.type || type || 'world') + ' detail';
      renderDetail(detail);
    } catch (error) {
      if (title) title.textContent = 'Detail unavailable';
      if (subtitle) subtitle.textContent = 'The selected world signal may have expired.';
      if (body) body.innerHTML = '<div class="mg-world-error">' + escapeHtml(error.message || 'Unable to load this World Canvas detail.') + '</div>';
    }
  }

  document.addEventListener('click', function (event) {
    var inRoot = root.contains(event.target);
    var inDrawer = drawer && drawer.contains(event.target);
    if (!inRoot && !inDrawer) return;
    var filter = event.target.closest('[data-world-filter]');
    if (filter) { activeFilter = filter.dataset.worldFilter || 'all'; applyFilter(); return; }
    var refresh = event.target.closest('[data-world-refresh]');
    if (refresh) { loadWorld(); return; }
    var close = event.target.closest('[data-world-drawer-close]');
    if (close) { closeDrawer(); return; }
    var node = event.target.closest('[data-world-node]');
    if (node) loadDetail(node.dataset.worldType, node.dataset.worldDetailId, node.dataset.worldNodeId);
  });

  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDrawer(); });
  portalDrawer();
  loadWorld();
  pollTimer = window.setInterval(loadWorld, 9000);
  window.addEventListener('beforeunload', function () { if (pollTimer) window.clearInterval(pollTimer); if (animationFrame) window.cancelAnimationFrame(animationFrame); });
})(window, document);
