window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var map = root.querySelector('[data-world-map]');
  if (!map) return;

  var frame = 0;
  var observer = null;
  var lastDensitySignature = '';

  function qsa(selector, scope) {
    return Array.from((scope || root).querySelectorAll(selector));
  }

  function number(value, fallback) {
    var parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function tier() {
    return root.dataset.worldZoomTier || 'world';
  }

  function isVisibleRect(rect, bounds, margin) {
    margin = margin || 0;
    return rect.right >= bounds.left - margin && rect.left <= bounds.right + margin && rect.bottom >= bounds.top - margin && rect.top <= bounds.bottom + margin;
  }

  function priority(element) {
    var score = 0;
    if (element.matches('[data-world-current-viewer],.is-current-viewer')) score += 1000;
    if (element.classList.contains('is-selected') || element.classList.contains('is-active')) score += 500;
    if (element.classList.contains('is-merchant')) score += 180;
    if (element.classList.contains('is-owned')) score += 120;
    if (element.matches('.mg-world-target-drop')) score += 90;
    return score;
  }

  function detailLimit(currentTier) {
    if (currentTier === 'detail') return 24;
    if (currentTier === 'store') return 36;
    if (currentTier === 'city') return 72;
    return Number.POSITIVE_INFINITY;
  }

  function applyViewportBudget() {
    var bounds = map.getBoundingClientRect();
    var currentTier = tier();
    var limit = detailLimit(currentTier);
    var visible = [];

    qsa('[data-world-node]', map).forEach(function (node) {
      var rect = node.getBoundingClientRect();
      var inViewport = isVisibleRect(rect, bounds, 96);
      node.dataset.worldInViewport = inViewport ? '1' : '0';
      node.classList.remove('is-detail-rendered', 'is-detail-lite');
      if (inViewport) visible.push(node);
    });

    visible.sort(function (left, right) { return priority(right) - priority(left); });
    visible.forEach(function (node, index) {
      var detailed = index < limit;
      node.classList.toggle('is-detail-rendered', detailed);
      node.classList.toggle('is-detail-lite', !detailed);
    });

    qsa('[data-world-node][data-world-in-viewport="0"]', map).forEach(function (node) {
      node.classList.add('is-detail-lite');
    });

    qsa('.mg-world-target-drop', map).forEach(function (zone) {
      var rect = zone.getBoundingClientRect();
      zone.dataset.worldInViewport = isVisibleRect(rect, bounds, 140) ? '1' : '0';
    });

    root.dataset.worldDetailBudget = Number.isFinite(limit) ? String(limit) : 'all';
  }

  function overlaps(left, right, padding) {
    padding = padding || 0;
    return !(left.right + padding <= right.left || left.left >= right.right + padding || left.bottom + padding <= right.top || left.top >= right.bottom + padding);
  }

  function shiftedRect(rect, x, y) {
    return {
      left: rect.left + x,
      right: rect.right + x,
      top: rect.top + y,
      bottom: rect.bottom + y,
      width: rect.width,
      height: rect.height
    };
  }

  function resetCollisionOffsets() {
    qsa('[data-world-node]', map).forEach(function (node) {
      node.style.removeProperty('--mg-world-collision-x');
      node.style.removeProperty('--mg-world-collision-y');
      node.classList.remove('is-world-collision-shifted');
    });
    qsa('.mg-world-target-drop', map).forEach(function (zone) {
      zone.style.removeProperty('--mg-world-label-shift-x');
      zone.style.removeProperty('--mg-world-label-shift-y');
      zone.classList.remove('is-world-label-shifted');
    });
  }

  function candidateOffsets(width, height) {
    var horizontal = Math.max(72, Math.min(180, width * 0.62));
    var vertical = Math.max(48, Math.min(130, height * 1.05));
    return [
      [0, 0],
      [0, -vertical],
      [0, vertical],
      [horizontal, 0],
      [-horizontal, 0],
      [horizontal, -vertical],
      [-horizontal, -vertical],
      [horizontal, vertical],
      [-horizontal, vertical],
      [0, -vertical * 1.8],
      [0, vertical * 1.8]
    ];
  }

  function chooseOffset(rect, placed, padding) {
    var offsets = candidateOffsets(rect.width, rect.height);
    for (var index = 0; index < offsets.length; index += 1) {
      var offset = offsets[index];
      var candidate = shiftedRect(rect, offset[0], offset[1]);
      var blocked = placed.some(function (other) { return overlaps(candidate, other, padding); });
      if (!blocked) return { x: offset[0], y: offset[1], rect: candidate };
    }
    var fallback = offsets[offsets.length - 1];
    return { x: fallback[0], y: fallback[1], rect: shiftedRect(rect, fallback[0], fallback[1]) };
  }

  function applyCollisionLayout() {
    resetCollisionOffsets();
    var currentTier = tier();
    if (['city', 'store', 'detail'].indexOf(currentTier) === -1) return;

    var mapBounds = map.getBoundingClientRect();
    var placed = [];
    var nodes = qsa('[data-world-node][data-world-in-viewport="1"].is-detail-rendered', map);
    if (currentTier === 'city') nodes = [];
    nodes.sort(function (left, right) { return priority(right) - priority(left); });

    nodes.forEach(function (node) {
      var rect = node.getBoundingClientRect();
      if (!isVisibleRect(rect, mapBounds, 40)) return;
      var chosen = chooseOffset(rect, placed, 8);
      if (chosen.x || chosen.y) {
        node.style.setProperty('--mg-world-collision-x', chosen.x.toFixed(1) + 'px');
        node.style.setProperty('--mg-world-collision-y', chosen.y.toFixed(1) + 'px');
        node.classList.add('is-world-collision-shifted');
      }
      placed.push(chosen.rect);
    });

    qsa('.mg-world-target-drop[data-world-in-viewport="1"]', map)
      .sort(function (left, right) { return priority(right) - priority(left); })
      .forEach(function (zone) {
        var label = zone.querySelector('em') || zone.querySelector('mark');
        if (!label || label.offsetParent === null) return;
        var rect = label.getBoundingClientRect();
        if (!isVisibleRect(rect, mapBounds, 60)) return;
        var chosen = chooseOffset(rect, placed, 6);
        if (chosen.x || chosen.y) {
          zone.style.setProperty('--mg-world-label-shift-x', chosen.x.toFixed(1) + 'px');
          zone.style.setProperty('--mg-world-label-shift-y', chosen.y.toFixed(1) + 'px');
          zone.classList.add('is-world-label-shifted');
        }
        placed.push(chosen.rect);
      });
  }

  function ensureDensityLayer() {
    var layer = map.querySelector('[data-world-density-layer]');
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = 'mg-world-density-layer';
    layer.dataset.worldDensityLayer = '1';
    layer.setAttribute('aria-hidden', 'true');
    map.insertBefore(layer, map.firstChild);
    return layer;
  }

  function renderDensity() {
    var layer = ensureDensityLayer();
    var bounds = map.getBoundingClientRect();
    var currentTier = tier();
    var cellSize = currentTier === 'world' ? 62 : currentTier === 'region' ? 74 : 96;
    var cells = {};

    qsa('[data-world-node].is-avatar,[data-world-node].is-merchant', map).forEach(function (node) {
      var rect = node.getBoundingClientRect();
      var x = rect.left + rect.width / 2 - bounds.left;
      var y = rect.top + rect.height / 2 - bounds.top;
      if (x < -40 || y < -40 || x > bounds.width + 40 || y > bounds.height + 40) return;
      var key = Math.floor(x / cellSize) + ':' + Math.floor(y / cellSize);
      if (!cells[key]) cells[key] = { x: 0, y: 0, count: 0, merchants: 0 };
      cells[key].x += x;
      cells[key].y += y;
      cells[key].count += 1;
      if (node.classList.contains('is-merchant')) cells[key].merchants += 1;
    });

    var spots = Object.keys(cells).map(function (key) {
      var cell = cells[key];
      return {
        x: cell.x / cell.count,
        y: cell.y / cell.count,
        count: cell.count,
        merchants: cell.merchants
      };
    }).filter(function (spot) { return spot.count >= 2; })
      .sort(function (left, right) { return right.count - left.count; })
      .slice(0, 48);

    var signature = spots.map(function (spot) {
      return [Math.round(spot.x), Math.round(spot.y), spot.count, spot.merchants].join(':');
    }).join('|');
    if (signature === lastDensitySignature) return;
    lastDensitySignature = signature;

    layer.innerHTML = spots.map(function (spot) {
      var size = Math.max(54, Math.min(190, 42 + Math.sqrt(spot.count) * 26));
      var opacity = Math.max(0.08, Math.min(0.34, 0.06 + spot.count * 0.025));
      var merchantRatio = spot.count ? spot.merchants / spot.count : 0;
      return '<span class="mg-world-density-spot" style="left:' + spot.x.toFixed(1) + 'px;top:' + spot.y.toFixed(1) + 'px;--mg-density-size:' + size.toFixed(1) + 'px;--mg-density-opacity:' + opacity.toFixed(3) + ';--mg-density-merchant:' + merchantRatio.toFixed(3) + '"></span>';
    }).join('');
    root.dataset.worldDensityCells = String(spots.length);
  }

  function run() {
    frame = 0;
    applyViewportBudget();
    applyCollisionLayout();
    renderDensity();
  }

  function schedule() {
    if (frame) return;
    frame = window.requestAnimationFrame(run);
  }

  document.addEventListener('mg:world-zoom-change', schedule);
  document.addEventListener('mg:world-target-drop-saved', schedule);
  document.addEventListener('mg:world-merchant-settings-saved', schedule);
  window.addEventListener('resize', schedule);

  observer = new MutationObserver(schedule);
  observer.observe(map, { childList: true, subtree: true });

  window.MicrogifterWorldDetail = {
    refresh: schedule,
    run: run
  };

  schedule();
})(window, document);
