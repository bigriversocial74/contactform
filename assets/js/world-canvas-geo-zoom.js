window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  var state = { zoom: 1, panX: 0, panY: 0, dragging: false, dragX: 0, dragY: 0, startX: 0, startY: 0 };
  var minZoom = 1;
  var maxZoom = 5;
  var avatarThreshold = 2.4;

  function qs(sel, scope) { return (scope || root).querySelector(sel); }
  function qsa(sel, scope) { return Array.from((scope || root).querySelectorAll(sel)); }
  function num(v, fallback) { var n = parseFloat(v); return Number.isFinite(n) ? n : fallback; }
  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function rect() { return map.getBoundingClientRect(); }

  function nodePoint(node) {
    var geo = node.dataset.worldGeo ? JSON.parse(node.dataset.worldGeo || '{}') : null;
    if (geo && Number.isFinite(parseFloat(geo.longitude)) && Number.isFinite(parseFloat(geo.latitude))) {
      var lng = clamp(parseFloat(geo.longitude), -180, 180);
      var lat = clamp(parseFloat(geo.latitude), -85, 85);
      return { x: ((lng + 180) / 360) * 100, y: ((85 - lat) / 170) * 100 };
    }
    return { x: num(node.dataset.worldTargetX || node.style.left, 50), y: num(node.dataset.worldTargetY || node.style.top, 50) };
  }

  function screenPoint(point) {
    var r = rect();
    return { x: state.panX + (point.x / 100) * r.width * state.zoom, y: state.panY + (point.y / 100) * r.height * state.zoom };
  }

  function mapPointFromScreen(x, y) {
    var r = rect();
    return { x: ((x - state.panX) / state.zoom / r.width) * 100, y: ((y - state.panY) / state.zoom / r.height) * 100 };
  }

  function ensureViewport() {
    var viewport = qs('[data-world-viewport]', map);
    if (!viewport) {
      viewport = document.createElement('div');
      viewport.className = 'mg-world-viewport';
      viewport.dataset.worldViewport = '1';
      map.insertBefore(viewport, map.firstChild);
    }
    ['.mg-world-reference-map-svg','.mg-world-flow-svg','[data-world-nodes]','[data-world-reward-radius-layer]'].forEach(function (sel) {
      qsa(sel, map).forEach(function (el) { if (el.parentNode !== viewport) viewport.appendChild(el); });
    });
    return viewport;
  }

  function ensureControls() {
    if (!qs('[data-world-square-zoom]', map)) {
      var controls = document.createElement('div');
      controls.className = 'mg-world-square-zoom';
      controls.dataset.worldSquareZoom = '1';
      controls.innerHTML = '<button type="button" data-geo-zoom-in>+</button><button type="button" data-geo-zoom-out>−</button><button type="button" data-geo-reset>⌖</button>';
      map.appendChild(controls);
    }
    if (!qs('[data-world-square-legend]', map)) {
      var legend = document.createElement('div');
      legend.className = 'mg-world-square-legend';
      legend.innerHTML = '<span class="is-avatar"><i></i>Users</span><span class="is-merchant"><i></i>Merchants</span><span class="is-reward"><i></i>Rewards</span><span class="is-claim"><i></i>Claims</span>';
      map.appendChild(legend);
    }
  }

  function clusterLayer() {
    var layer = qs('[data-world-geo-cluster-layer]', map);
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = 'mg-world-geo-cluster-layer';
    layer.dataset.worldGeoClusterLayer = '1';
    map.appendChild(layer);
    return layer;
  }

  function applyViewport() {
    var viewport = ensureViewport();
    viewport.style.transform = 'translate(' + state.panX + 'px,' + state.panY + 'px) scale(' + state.zoom + ')';
    root.style.setProperty('--mg-world-zoom', String(state.zoom));
    root.dataset.worldZoomLevel = String(Math.round(state.zoom));
    root.dataset.worldLiveAvatarMotion = state.zoom >= 3 ? 'on' : 'off';
    root.dataset.worldAvatarVisibility = state.zoom >= avatarThreshold ? 'show' : 'clustered';
    renderClusters();
    renderLabel();
  }

  function setZoom(next, center) {
    var oldZoom = state.zoom;
    var nextZoom = clamp(next, minZoom, maxZoom);
    var r = rect();
    var cx = center ? center.x : r.width / 2;
    var cy = center ? center.y : r.height / 2;
    var before = mapPointFromScreen(cx, cy);
    state.zoom = nextZoom;
    state.panX = cx - (before.x / 100) * r.width * state.zoom;
    state.panY = cy - (before.y / 100) * r.height * state.zoom;
    if (oldZoom !== nextZoom) applyViewport();
  }

  function centerOn(point, nextZoom) {
    var r = rect();
    state.zoom = clamp(nextZoom || state.zoom + 1, minZoom, maxZoom);
    state.panX = r.width / 2 - (point.x / 100) * r.width * state.zoom;
    state.panY = r.height / 2 - (point.y / 100) * r.height * state.zoom;
    applyViewport();
  }

  function nodesForClusters() {
    return qsa('[data-world-node]').filter(function (node) {
      return !node.classList.contains('is-campaign') && node.offsetParent !== null;
    }).map(function (node) { return { node: node, point: nodePoint(node) }; });
  }

  function renderClusters() {
    var layer = clusterLayer();
    var items = nodesForClusters();
    items.forEach(function (item) { item.node.classList.remove('is-cluster-hidden'); });
    if (state.zoom >= avatarThreshold) { layer.innerHTML = ''; return; }
    var radius = state.zoom < 1.5 ? 92 : 70;
    var groups = [];
    items.forEach(function (item) {
      var s = screenPoint(item.point);
      var target = groups.find(function (g) {
        var dx = g.sx / g.items.length - s.x;
        var dy = g.sy / g.items.length - s.y;
        return Math.sqrt(dx * dx + dy * dy) < radius;
      });
      if (!target) { target = { sx: 0, sy: 0, x: 0, y: 0, items: [] }; groups.push(target); }
      target.sx += s.x; target.sy += s.y; target.x += item.point.x; target.y += item.point.y; target.items.push(item);
    });
    layer.innerHTML = groups.filter(function (g) { return g.items.length > 1; }).map(function (g, index) {
      g.items.forEach(function (item) { item.node.classList.add('is-cluster-hidden'); });
      var count = g.items.length;
      var x = g.x / count;
      var y = g.y / count;
      var size = Math.max(42, Math.min(108, 30 + count * 9));
      var cls = count > 8 ? 'is-hot' : count > 4 ? 'is-warm' : '';
      return '<button type="button" class="mg-world-geo-cluster ' + cls + '" style="left:' + x + '%;top:' + y + '%;width:' + size + 'px;height:' + size + 'px" data-geo-cluster data-cluster-x="' + x + '" data-cluster-y="' + y + '"><strong>' + count + '</strong><small>cluster</small></button>';
    }).join('');
  }

  function renderLabel() {
    var label = qs('[data-world-zoom-label]', map);
    if (!label) {
      label = document.createElement('div');
      label.className = 'mg-world-zoom-label';
      label.dataset.worldZoomLabel = '1';
      map.appendChild(label);
    }
    var text = state.zoom >= 3 ? 'Store view · live avatars moving' : state.zoom >= avatarThreshold ? 'City view · avatars visible' : 'World view · stable clusters';
    label.innerHTML = '<i></i>' + text;
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-geo-zoom-in]')) { setZoom(state.zoom + 0.6); return; }
    if (event.target.closest('[data-geo-zoom-out]')) { setZoom(state.zoom - 0.6); return; }
    if (event.target.closest('[data-geo-reset]')) { state.zoom = 1; state.panX = 0; state.panY = 0; applyViewport(); return; }
    var cluster = event.target.closest('[data-geo-cluster]');
    if (cluster) { centerOn({ x: num(cluster.dataset.clusterX, 50), y: num(cluster.dataset.clusterY, 50) }, state.zoom + 1.1); }
  }, true);

  map.addEventListener('wheel', function (event) {
    event.preventDefault();
    var r = rect();
    setZoom(state.zoom + (event.deltaY < 0 ? 0.35 : -0.35), { x: event.clientX - r.left, y: event.clientY - r.top });
  }, { passive: false });

  map.addEventListener('pointerdown', function (event) {
    if (event.target.closest('button')) return;
    state.dragging = true;
    state.dragX = event.clientX;
    state.dragY = event.clientY;
    state.startX = state.panX;
    state.startY = state.panY;
    map.setPointerCapture(event.pointerId);
  });
  map.addEventListener('pointermove', function (event) {
    if (!state.dragging) return;
    state.panX = state.startX + event.clientX - state.dragX;
    state.panY = state.startY + event.clientY - state.dragY;
    applyViewport();
  });
  map.addEventListener('pointerup', function () { state.dragging = false; });
  map.addEventListener('pointercancel', function () { state.dragging = false; });

  ensureControls();
  applyViewport();
  window.setInterval(function () { ensureViewport(); renderClusters(); }, 1400);
})(window, document);
