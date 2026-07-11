window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  function number(value, fallback) {
    var parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function currentZoom() {
    return Math.max(1, number(root.style.getPropertyValue('--mg-world-zoom'), number(root.dataset.worldZoomLevel, 1)));
  }

  function currentTier() {
    return root.dataset.worldZoomTier || 'world';
  }

  function baseSize(element) {
    var stored = number(element.dataset.worldZoneBaseSize, 0);
    if (stored > 0) return stored;
    var inline = number(element.style.width, 0);
    var measured = inline > 0 ? inline : Math.max(element.offsetWidth || 0, element.offsetHeight || 0, 72);
    element.dataset.worldZoneBaseSize = String(measured);
    return measured;
  }

  function screenSize(base, zoom) {
    return Math.max(26, Math.min(140, base / Math.pow(zoom, 0.72)));
  }

  function apply() {
    var zoom = currentZoom();
    var inverse = 1 / zoom;
    var tier = currentTier();
    root.style.setProperty('--mg-world-zone-scale', inverse.toFixed(6));

    root.querySelectorAll('.mg-world-target-drop').forEach(function (element) {
      var size = screenSize(baseSize(element), zoom);
      element.dataset.worldZoneDetail = tier;
      element.style.setProperty('--mg-zone-screen-size', size.toFixed(2) + 'px');
      element.style.setProperty('--mg-zone-scale', inverse.toFixed(6));
      element.style.removeProperty('transform-origin');
      ['b','em','small','i','mark'].forEach(function (selector) {
        element.querySelectorAll(selector).forEach(function (child) { child.style.removeProperty('transform'); child.style.removeProperty('transform-origin'); });
      });
    });
  }

  new MutationObserver(apply).observe(root, { attributes: true, attributeFilter: ['style','data-world-zoom-level','data-world-zoom-tier'] });
  document.addEventListener('mg:world-zoom-change', apply);
  document.addEventListener('mg:world-target-drop-saved', apply);
  document.addEventListener('mg:world-merchant-settings-saved', apply);
  window.addEventListener('resize', apply);
  window.setInterval(apply, 900);
  apply();
})(window, document);
