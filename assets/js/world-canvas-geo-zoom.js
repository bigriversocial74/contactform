window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var zoom = 1;
  function qs(sel, scope) { return (scope || root).querySelector(sel); }
  function qsa(sel, scope) { return Array.from((scope || root).querySelectorAll(sel)); }
  function pct(v) { var n = parseFloat(v); return Number.isFinite(n) ? n : 50; }
  function setZoom(next) {
    zoom = Math.max(1, Math.min(4, next));
    root.style.setProperty('--mg-world-zoom', String(zoom));
    root.dataset.worldZoomLevel = String(Math.round(zoom));
    root.dataset.worldLiveAvatarMotion = zoom >= 3 ? 'on' : 'off';
    renderClusters();
    renderLabel();
  }
  function addControls() {
    var map = qs('[data-world-map]');
    if (!map) return;
    if (!qs('[data-world-square-zoom]', map)) {
      var controls = document.createElement('div');
      controls.className = 'mg-world-square-zoom';
      controls.innerHTML = '<button type="button" data-geo-zoom-in>+</button><button type="button" data-geo-zoom-out>−</button>';
      map.appendChild(controls);
    }
    if (!qs('[data-world-square-legend]', map)) {
      var legend = document.createElement('div');
      legend.className = 'mg-world-square-legend';
      legend.innerHTML = '<span class="is-avatar"><i></i>Users</span><span class="is-merchant"><i></i>Merchants</span><span class="is-reward"><i></i>Rewards</span><span class="is-claim"><i></i>Claims</span>';
      map.appendChild(legend);
    }
  }
  function layer() {
    var map = qs('[data-world-map]');
    if (!map) return null;
    var el = qs('[data-world-geo-cluster-layer]', map);
    if (el) return el;
    el = document.createElement('div');
    el.className = 'mg-world-geo-cluster-layer';
    el.dataset.worldGeoClusterLayer = '1';
    map.appendChild(el);
    return el;
  }
  function p(node) { return { x:pct(node.style.left || node.dataset.worldTargetX), y:pct(node.style.top || node.dataset.worldTargetY) }; }
  function bucket() { return zoom < 1.5 ? 18 : zoom < 2.5 ? 12 : 7; }
  function renderClusters() {
    var el = layer();
    if (!el) return;
    var nodes = qsa('[data-world-node]').filter(function (n) { return !n.classList.contains('is-campaign') && n.offsetParent !== null; });
    nodes.forEach(function (n) { n.classList.remove('is-cluster-hidden'); });
    if (zoom >= 3) { el.innerHTML = ''; return; }
    var groups = {}, b = bucket();
    nodes.forEach(function (n) {
      var pos = p(n), key = Math.round(pos.x / b) + ':' + Math.round(pos.y / b);
      (groups[key] = groups[key] || []).push({ node:n, x:pos.x, y:pos.y });
    });
    var html = [];
    Object.keys(groups).forEach(function (key) {
      var g = groups[key];
      if (g.length < 2) return;
      var sx = 0, sy = 0;
      g.forEach(function (item) { sx += item.x; sy += item.y; item.node.classList.add('is-cluster-hidden'); });
      var count = g.length, size = Math.max(38, Math.min(96, 30 + count * 9));
      var cls = count > 8 ? 'is-hot' : count > 4 ? 'is-warm' : '';
      html.push('<button type="button" class="mg-world-geo-cluster ' + cls + '" style="left:' + (sx/count) + '%;top:' + (sy/count) + '%;width:' + size + 'px;height:' + size + 'px" data-geo-cluster><strong>' + count + '</strong><small>cluster</small></button>');
    });
    el.innerHTML = html.join('');
  }
  function renderLabel() {
    var map = qs('[data-world-map]');
    if (!map) return;
    var label = qs('[data-world-zoom-label]', map);
    if (!label) {
      label = document.createElement('div');
      label.className = 'mg-world-zoom-label';
      label.dataset.worldZoomLabel = '1';
      map.appendChild(label);
    }
    var text = zoom >= 3 ? 'Store view · live avatars moving' : zoom >= 2 ? 'City view · clusters spreading' : 'World view · stable clusters';
    label.innerHTML = '<i></i>' + text;
  }
  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-geo-zoom-in]')) { setZoom(zoom + .5); return; }
    if (event.target.closest('[data-geo-zoom-out]')) { setZoom(zoom - .5); return; }
    if (event.target.closest('[data-geo-cluster]')) { setZoom(zoom + 1); }
  }, true);
  addControls();
  setZoom(1);
  window.setInterval(renderClusters, 1500);
})(window, document);
