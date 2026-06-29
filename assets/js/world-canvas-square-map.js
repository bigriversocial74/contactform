window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  var zoom = 1;
  function qs(sel, scope) { return (scope || root).querySelector(sel); }
  function qsa(sel, scope) { return Array.from((scope || root).querySelectorAll(sel)); }
  function setZoom(value) {
    zoom = Math.max(1, Math.min(3, value));
    root.style.setProperty('--mg-world-zoom', String(zoom));
    root.dataset.worldZoomLevel = String(Math.round(zoom));
    var label = qs('[data-world-square-zoom-label]');
    if (label) label.textContent = Math.round(zoom * 100) + '%';
  }
  function copyNodeTitles() {
    qsa('[data-world-node]').forEach(function (node) {
      if (node.dataset.worldTitle) return;
      var title = qs('.mg-world-node-title', node);
      var subtitle = qs('.mg-world-node-subtitle', node);
      node.dataset.worldTitle = title && title.textContent.trim() ? title.textContent.trim() : (subtitle && subtitle.textContent.trim() ? subtitle.textContent.trim() : 'World avatar');
    });
  }
  function addControls() {
    var map = qs('[data-world-map]');
    if (!map) return;
    if (!qs('[data-world-square-zoom]', map)) {
      var controls = document.createElement('div');
      controls.className = 'mg-world-square-zoom';
      controls.dataset.worldSquareZoom = '1';
      controls.innerHTML = '<button type="button" data-world-square-minus>−</button><span data-world-square-zoom-label>100%</span><button type="button" data-world-square-plus>+</button>';
      map.appendChild(controls);
    }
    if (!qs('[data-world-square-legend]', map)) {
      var legend = document.createElement('div');
      legend.className = 'mg-world-square-legend';
      legend.dataset.worldSquareLegend = '1';
      legend.innerHTML = '<span class="is-avatar"><i></i>User avatars</span><span class="is-merchant"><i></i>Merchant avatars</span><span class="is-reward"><i></i>Rewards</span><span class="is-claim"><i></i>Claims</span>';
      map.appendChild(legend);
    }
  }
  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-world-square-plus]')) setZoom(zoom + 0.5);
    if (event.target.closest('[data-world-square-minus]')) setZoom(zoom - 0.5);
  });
  var state = qs('[data-world-state]');
  if (state) state.textContent = 'Square world view: user and merchant avatars display as dots. Zoom in to enlarge dots and reveal labels.';
  addControls();
  setZoom(1);
  copyNodeTitles();
  window.setInterval(copyNodeTitles, 1500);
})(window, document);
