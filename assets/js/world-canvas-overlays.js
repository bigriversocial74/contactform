window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  var updateTimer = null;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function qsa(selector, scope) { return Array.from((scope || root).querySelectorAll(selector)); }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function num(value, fallback) {
    var parsed = parseFloat(String(value || '').replace('%', ''));
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function ensureLayer(name, className) {
    var map = qs('[data-world-map]');
    if (!map) return null;
    var layer = qs('[data-' + name + ']');
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = className;
    layer.setAttribute('data-' + name, '');
    var nodeLayer = qs('[data-world-nodes]', map);
    if (nodeLayer && nodeLayer.parentNode) nodeLayer.parentNode.insertBefore(layer, nodeLayer);
    else map.appendChild(layer);
    return layer;
  }

  function collectNodes() {
    return qsa('[data-world-node]').filter(function (node) { return !node.hidden; }).map(function (node) {
      return {
        id: node.dataset.worldNodeId || '',
        type: node.dataset.worldType || 'node',
        location: node.dataset.worldLocationKey || '',
        conversation: node.dataset.worldConversationKey || '',
        tags: String(node.dataset.worldAffinity || '').split(',').filter(Boolean),
        x: num(node.style.left || node.dataset.worldTargetX, 50),
        y: num(node.style.top || node.dataset.worldTargetY, 50),
        title: (node.querySelector('.mg-world-node-copy strong') || {}).textContent || 'World signal'
      };
    });
  }

  function groupBy(nodes, key) {
    var groups = new Map();
    nodes.forEach(function (node) {
      var value = node[key] || '';
      if (!value) return;
      if (!groups.has(value)) groups.set(value, []);
      groups.get(value).push(node);
    });
    return Array.from(groups.entries()).map(function (entry) { return { key: entry[0], nodes: entry[1] }; });
  }

  function centerOf(nodes) {
    var total = nodes.reduce(function (sum, node) { sum.x += node.x; sum.y += node.y; return sum; }, { x: 0, y: 0 });
    var count = Math.max(1, nodes.length);
    return { x: total.x / count, y: total.y / count };
  }

  function dominantType(nodes) {
    var counts = {};
    nodes.forEach(function (node) { counts[node.type] = (counts[node.type] || 0) + 1; });
    return Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; })[0] || 'avatar';
  }

  function renderHeatZones(nodes) {
    var layer = ensureLayer('world-heat-layer', 'mg-world-heat-layer');
    if (!layer) return;
    var groups = groupBy(nodes, 'location').filter(function (group) { return group.nodes.length >= 2; }).slice(0, 12);
    layer.innerHTML = groups.map(function (group) {
      var center = centerOf(group.nodes);
      var type = dominantType(group.nodes);
      var size = Math.min(34, 12 + group.nodes.length * 4);
      return '<div class="mg-world-heat-zone is-' + escapeHtml(type) + '" style="left:' + center.x.toFixed(2) + '%;top:' + center.y.toFixed(2) + '%;width:' + size + 'vw;height:' + size + 'vw"><span>' + group.nodes.length + '</span></div>';
    }).join('');
  }

  function conversationTitle(group) {
    var avatars = group.nodes.filter(function (node) { return node.type === 'avatar'; }).length;
    var rewards = group.nodes.filter(function (node) { return node.type === 'reward'; }).length;
    var claims = group.nodes.filter(function (node) { return node.type === 'claim'; }).length;
    if (claims) return claims + ' claim signal' + (claims === 1 ? '' : 's');
    if (rewards) return rewards + ' reward movement' + (rewards === 1 ? '' : 's');
    if (avatars) return avatars + ' avatar conversation' + (avatars === 1 ? '' : 's');
    return group.nodes.length + ' world signals';
  }

  function renderConversationClusters(nodes) {
    var layer = ensureLayer('world-cluster-layer', 'mg-world-cluster-layer');
    if (!layer) return;
    var groups = groupBy(nodes, 'conversation').filter(function (group) { return group.nodes.length >= 2; }).slice(0, 10);
    layer.innerHTML = groups.map(function (group) {
      var center = centerOf(group.nodes);
      var tag = group.nodes.reduce(function (picked, node) { return picked || node.tags[0] || ''; }, '');
      return '<button type="button" class="mg-world-cluster" data-world-cluster-key="' + escapeHtml(group.key) + '" style="left:' + center.x.toFixed(2) + '%;top:' + center.y.toFixed(2) + '%"><strong>' + escapeHtml(conversationTitle(group)) + '</strong><span>' + escapeHtml(tag || 'same location') + '</span></button>';
    }).join('');
  }

  function renderOverlays() {
    var nodes = collectNodes();
    renderHeatZones(nodes);
    renderConversationClusters(nodes);
  }

  function scheduleRender() {
    if (updateTimer) window.clearTimeout(updateTimer);
    updateTimer = window.setTimeout(renderOverlays, 160);
  }

  document.addEventListener('click', function (event) {
    var cluster = event.target.closest('[data-world-cluster-key]');
    if (!cluster || !root.contains(cluster)) return;
    qsa('[data-world-node]').forEach(function (node) {
      var match = node.dataset.worldConversationKey === cluster.dataset.worldClusterKey;
      node.classList.toggle('is-active', match);
    });
  });

  var nodeLayer = qs('[data-world-nodes]');
  if (nodeLayer) {
    new MutationObserver(scheduleRender).observe(nodeLayer, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'hidden', 'class'] });
  }
  window.setInterval(renderOverlays, 1200);
  scheduleRender();
})(window, document);
