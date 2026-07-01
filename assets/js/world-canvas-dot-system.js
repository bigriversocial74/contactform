window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  function qs(sel, scope) { return (scope || root).querySelector(sel); }
  function qsa(sel, scope) { return Array.from((scope || root).querySelectorAll(sel)); }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
  function text(sel, scope) { var node = qs(sel, scope); return node ? node.textContent.trim() : ''; }
  function pct(value) { var n = parseFloat(value); return Number.isFinite(n) ? n : 50; }

  var activePopover = null;

  function nodeInfo(node) {
    var type = node.dataset.worldType || 'avatar';
    var title = node.dataset.worldTitle || text('.mg-world-node-title', node) || (type === 'merchant' ? 'Merchant avatar' : 'User avatar');
    var subtitle = text('.mg-world-node-subtitle', node) || 'World Canvas signal';
    var meta = text('.mg-world-node-meta', node) || '';
    var location = node.dataset.worldLocationKey || 'Affinity placed';
    var affinity = node.dataset.worldAffinity || 'rewards, activity, location';
    var geo = node.dataset.worldGeoLocked === '1' || node.classList.contains('is-geo') ? 'Geo anchored' : 'Affinity placed';
    return { type:type, title:title, subtitle:subtitle, meta:meta, location:location, affinity:affinity, geo:geo };
  }

  function closePopover() {
    if (activePopover && activePopover.parentNode) activePopover.parentNode.removeChild(activePopover);
    activePopover = null;
    qsa('[data-world-node].is-selected').forEach(function (node) { node.classList.remove('is-selected'); });
  }

  function openPopover(node) {
    var map = qs('[data-world-map]');
    if (!map) return;
    closePopover();
    node.classList.add('is-selected');
    var info = nodeInfo(node);
    var left = pct(node.style.left || node.dataset.worldTargetX || 50);
    var top = pct(node.style.top || node.dataset.worldTargetY || 50);
    var pop = document.createElement('article');
    pop.className = 'mg-world-dot-popover is-' + info.type;
    pop.style.left = left + '%';
    pop.style.top = top + '%';
    pop.innerHTML = '<header><span>' + esc(info.type) + '</span><button type="button" data-world-popover-close>×</button></header><strong>' + esc(info.title) + '</strong><p>' + esc(info.subtitle) + '</p><dl><dt>Status</dt><dd>' + esc(info.meta || 'Active') + '</dd><dt>Placement</dt><dd>' + esc(info.geo) + '</dd><dt>Location</dt><dd>' + esc(info.location) + '</dd><dt>Affinity</dt><dd>' + esc(info.affinity) + '</dd></dl><footer><button type="button" data-world-open-detail>Open detail</button></footer>';
    pop.dataset.worldTargetNodeId = node.dataset.worldNodeId || '';
    map.appendChild(pop);
    activePopover = pop;
  }

  function ensureClusterLayer() {
    var map = qs('[data-world-map]');
    if (!map) return null;
    var layer = qs('[data-world-dot-cluster-layer]', map);
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = 'mg-world-dot-cluster-layer';
    layer.dataset.worldDotClusterLayer = '1';
    map.appendChild(layer);
    return layer;
  }

  function ensureRadiusLayer() {
    var map = qs('[data-world-map]');
    if (!map) return null;
    var layer = qs('[data-world-reward-radius-layer]', map);
    if (layer) return layer;
    layer = document.createElement('div');
    layer.className = 'mg-world-reward-radius-layer';
    layer.dataset.worldRewardRadiusLayer = '1';
    map.appendChild(layer);
    return layer;
  }

  function groupKey(node) {
    var x = Math.round(pct(node.style.left || node.dataset.worldTargetX || 50) / 10) * 10;
    var y = Math.round(pct(node.style.top || node.dataset.worldTargetY || 50) / 10) * 10;
    return x + ':' + y;
  }

  function renderClusters() {
    var layer = ensureClusterLayer();
    if (!layer) return;
    var zoom = parseFloat(getComputedStyle(root).getPropertyValue('--mg-world-zoom')) || 1;
    var nodes = qsa('[data-world-node]').filter(function (node) { return !node.classList.contains('is-campaign') && node.offsetParent !== null; });
    nodes.forEach(function (node) { node.classList.remove('is-cluster-hidden'); });
    if (zoom >= 2) { layer.innerHTML = ''; return; }
    var groups = {};
    nodes.forEach(function (node) {
      var key = groupKey(node);
      groups[key] = groups[key] || [];
      groups[key].push(node);
    });
    var html = [];
    Object.keys(groups).forEach(function (key) {
      var group = groups[key];
      if (group.length < 3) return;
      var sx = 0, sy = 0, avatar = 0, reward = 0;
      group.forEach(function (node) {
        sx += pct(node.style.left || node.dataset.worldTargetX || 50);
        sy += pct(node.style.top || node.dataset.worldTargetY || 50);
        if (node.classList.contains('is-avatar')) avatar++;
        if (node.classList.contains('is-reward')) reward++;
        node.classList.add('is-cluster-hidden');
      });
      var x = sx / group.length;
      var y = sy / group.length;
      var cls = reward ? 'is-reward' : avatar ? 'is-avatar' : 'is-mixed';
      html.push('<button type="button" class="mg-world-dot-cluster ' + cls + '" style="left:' + x + '%;top:' + y + '%" data-world-cluster-count="' + group.length + '"><strong>' + group.length + '</strong><span>dots</span></button>');
    });
    layer.innerHTML = html.join('');
  }

  function renderRewardRadius() {
    var layer = ensureRadiusLayer();
    if (!layer) return;
    var rewards = qsa('[data-world-node].is-reward');
    layer.innerHTML = rewards.map(function (node) {
      var x = pct(node.style.left || node.dataset.worldTargetX || 50);
      var y = pct(node.style.top || node.dataset.worldTargetY || 50);
      return '<span class="mg-world-reward-radius" style="left:' + x + '%;top:' + y + '%;width:120px;height:120px"></span>';
    }).join('');
  }

  function addFocusStrip() {
    var head = qs('.mg-world-stage-head');
    if (!head || qs('[data-world-focus-strip]')) return;
    var strip = document.createElement('div');
    strip.className = 'mg-world-focus-strip';
    strip.dataset.worldFocusStrip = '1';
    strip.innerHTML = '<button type="button" class="is-active" data-world-focus="all">All</button><button type="button" data-world-focus="avatar">Avatars</button><button type="button" data-world-focus="merchant">Merchants</button><button type="button" data-world-focus="reward">Rewards</button><button type="button" data-world-focus="claim">Claims</button><button type="button" data-world-focus="affinity">Affinity</button>';
    head.parentNode.insertBefore(strip, head.nextSibling);
  }

  function loadIdentityLayer() {
    if (!document.querySelector('link[data-world-identity-layer-css]')) {
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = '/assets/css/world-canvas-identity-layer.css';
      link.dataset.worldIdentityLayerCss = '1';
      document.head.appendChild(link);
    }
    if (!document.querySelector('script[data-world-identity-layer-js]')) {
      var script = document.createElement('script');
      script.src = '/assets/js/world-canvas-identity-layer.js';
      script.defer = true;
      script.dataset.worldIdentityLayerJs = '1';
      document.body.appendChild(script);
    }
  }

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-world-popover-close]')) { closePopover(); return; }
    if (event.target.closest('[data-world-open-detail]')) {
      var id = activePopover ? activePopover.dataset.worldTargetNodeId : '';
      var node = id ? qs('[data-world-node-id="' + id + '"]') : null;
      if (node) node.dispatchEvent(new MouseEvent('click', { bubbles:true }));
      closePopover();
      return;
    }
    var focus = event.target.closest('[data-world-focus]');
    if (focus && root.contains(focus)) {
      root.dataset.worldFocus = focus.dataset.worldFocus || 'all';
      qsa('[data-world-focus]').forEach(function (button) { button.classList.toggle('is-active', button === focus); });
      return;
    }
    var cluster = event.target.closest('.mg-world-dot-cluster');
    if (cluster) {
      root.style.setProperty('--mg-world-zoom', '2');
      root.dataset.worldZoomLevel = '2';
      renderClusters();
      return;
    }
    var node = event.target.closest('[data-world-node]');
    if (node && root.contains(node)) {
      event.preventDefault();
      event.stopPropagation();
      openPopover(node);
      return;
    }
    if (!event.target.closest('.mg-world-dot-popover')) closePopover();
  }, true);

  addFocusStrip();
  loadIdentityLayer();
  renderClusters();
  renderRewardRadius();
  window.setInterval(function () { renderClusters(); renderRewardRadius(); }, 1800);
})(window, document);