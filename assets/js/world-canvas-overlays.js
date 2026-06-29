window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  var updateTimer = null;
  var activeMode = root.getAttribute('data-world-mode') || 'live';

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
        geo: node.dataset.worldGeoLocked === 'true',
        x: num(node.style.left || node.dataset.worldTargetX, 50),
        y: num(node.style.top || node.dataset.worldTargetY, 50),
        title: (node.querySelector('.mg-world-node-copy strong') || {}).textContent || 'World signal',
        subtitle: (node.querySelector('.mg-world-node-copy span') || {}).textContent || '',
        meta: (node.querySelector('.mg-world-node-meta') || {}).textContent || ''
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

  function modeAllows(name) {
    if (activeMode === 'live') return true;
    if (activeMode === 'heat') return name === 'heat';
    if (activeMode === 'conversations') return name === 'cluster';
    if (activeMode === 'geo') return name === 'geo';
    if (activeMode === 'movement') return name === 'movement';
    return true;
  }

  function applyMode(nodes) {
    root.setAttribute('data-world-mode', activeMode);
    qsa('[data-world-mode-button]').forEach(function (button) {
      button.classList.toggle('is-active', button.dataset.worldModeButton === activeMode);
    });
    qsa('[data-world-node]').forEach(function (node) {
      var type = node.dataset.worldType || '';
      var geo = node.dataset.worldGeoLocked === 'true';
      var dim = false;
      if (activeMode === 'geo') dim = !geo;
      if (activeMode === 'movement') dim = !(type === 'reward' || type === 'claim' || type === 'avatar');
      if (activeMode === 'conversations') dim = !(node.dataset.worldConversationKey || '').length;
      node.classList.toggle('is-dim', dim);
    });
  }

  function renderHeatZones(nodes) {
    var layer = ensureLayer('world-heat-layer', 'mg-world-heat-layer');
    if (!layer) return;
    if (!modeAllows('heat') && activeMode !== 'live') { layer.innerHTML = ''; return; }
    var groups = groupBy(nodes, 'location').filter(function (group) { return group.nodes.length >= 2; }).slice(0, 12);
    layer.innerHTML = groups.map(function (group) {
      var center = centerOf(group.nodes);
      var type = dominantType(group.nodes);
      var size = Math.min(34, 12 + group.nodes.length * 4);
      return '<div class="mg-world-heat-zone is-' + escapeHtml(type) + '" data-world-heat-key="' + escapeHtml(group.key) + '" style="left:' + center.x.toFixed(2) + '%;top:' + center.y.toFixed(2) + '%;width:' + size + 'vw;height:' + size + 'vw"><span>' + group.nodes.length + '</span></div>';
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
    if (!modeAllows('cluster') && activeMode !== 'live') { layer.innerHTML = ''; return; }
    var groups = groupBy(nodes, 'conversation').filter(function (group) { return group.nodes.length >= 2; }).slice(0, 10);
    layer.innerHTML = groups.map(function (group) {
      var center = centerOf(group.nodes);
      var tag = group.nodes.reduce(function (picked, node) { return picked || node.tags[0] || ''; }, '');
      return '<button type="button" class="mg-world-cluster" data-world-cluster-key="' + escapeHtml(group.key) + '" style="left:' + center.x.toFixed(2) + '%;top:' + center.y.toFixed(2) + '%"><strong>' + escapeHtml(conversationTitle(group)) + '</strong><span>' + escapeHtml(tag || 'same location') + '</span></button>';
    }).join('');
  }

  function renderInsights(nodes) {
    var panel = qs('[data-world-insights]');
    if (!panel) return;
    var locationGroups = groupBy(nodes, 'location').filter(function (group) { return group.nodes.length >= 2; });
    var conversationGroups = groupBy(nodes, 'conversation').filter(function (group) { return group.nodes.length >= 2; });
    var geoCount = nodes.filter(function (node) { return node.geo; }).length;
    var rewardCount = nodes.filter(function (node) { return node.type === 'reward'; }).length;
    var claimCount = nodes.filter(function (node) { return node.type === 'claim'; }).length;
    var strongest = locationGroups.sort(function (a, b) { return b.nodes.length - a.nodes.length; })[0];
    var cards = [
      ['Avatar clusters', conversationGroups.length, 'Active avatar-to-avatar conversation groups.'],
      ['Heat zones', locationGroups.length, 'Same-location demand pockets forming on the map.'],
      ['Geo anchors', geoCount, 'Avatars currently placed from saved coordinate anchors.'],
      ['Movement', rewardCount + claimCount, 'Reward and claim signals moving through the world.']
    ];
    if (strongest) cards.push(['Strongest zone', strongest.nodes.length, 'Highest current same-location activity pocket.']);
    panel.innerHTML = '<div class="mg-world-insight-grid">' + cards.map(function (card) {
      return '<article><span>' + escapeHtml(card[0]) + '</span><strong>' + escapeHtml(card[1]) + '</strong><p>' + escapeHtml(card[2]) + '</p></article>';
    }).join('') + '</div>';
  }

  function openClusterDrawer(key) {
    var drawer = qs('[data-world-drawer]') || document.querySelector('[data-world-drawer]');
    if (!drawer) return;
    if (drawer.parentElement !== document.body) document.body.appendChild(drawer);
    var nodes = collectNodes().filter(function (node) { return node.conversation === key; });
    var title = drawer.querySelector('[data-world-drawer-title]');
    var subtitle = drawer.querySelector('[data-world-drawer-subtitle]');
    var type = drawer.querySelector('[data-world-drawer-type]');
    var body = drawer.querySelector('[data-world-drawer-body]');
    if (type) type.textContent = 'Conversation cluster';
    if (title) title.textContent = nodes.length + ' connected world signals';
    if (subtitle) subtitle.textContent = 'Same conversation key, similar affinity, or same merchant location.';
    if (body) {
      body.innerHTML = '<section class="mg-world-detail-note">This cluster is generated from anonymous World Canvas signals. Private customer identity stays inside the owned merchant Store Canvas.</section><section class="mg-world-cluster-list">' + nodes.slice(0, 12).map(function (node) {
        return '<article><b>' + escapeHtml(node.type) + '</b><strong>' + escapeHtml(node.title) + '</strong><span>' + escapeHtml(node.subtitle || node.meta) + '</span></article>';
      }).join('') + '</section>';
    }
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
  }

  function renderOverlays() {
    var nodes = collectNodes();
    applyMode(nodes);
    renderHeatZones(nodes);
    renderConversationClusters(nodes);
    renderInsights(nodes);
  }

  function scheduleRender() {
    if (updateTimer) window.clearTimeout(updateTimer);
    updateTimer = window.setTimeout(renderOverlays, 160);
  }

  document.addEventListener('click', function (event) {
    var mode = event.target.closest('[data-world-mode-button]');
    if (mode && root.contains(mode)) {
      activeMode = mode.dataset.worldModeButton || 'live';
      scheduleRender();
      return;
    }
    var cluster = event.target.closest('[data-world-cluster-key]');
    if (!cluster || !root.contains(cluster)) return;
    qsa('[data-world-node]').forEach(function (node) {
      var match = node.dataset.worldConversationKey === cluster.dataset.worldClusterKey;
      node.classList.toggle('is-active', match);
    });
    openClusterDrawer(cluster.dataset.worldClusterKey || '');
  });

  var nodeLayer = qs('[data-world-nodes]');
  if (nodeLayer) {
    new MutationObserver(scheduleRender).observe(nodeLayer, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'hidden', 'class'] });
  }
  window.setInterval(renderOverlays, 1200);
  scheduleRender();
})(window, document);
