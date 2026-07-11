window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;

  function number(value, fallback) {
    var parsed = parseFloat(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
  }

  function apply() {
    var zoom = Math.max(1, number(root.style.getPropertyValue('--mg-world-zoom'), number(root.dataset.worldZoomLevel, 1)));
    root.style.setProperty('--mg-world-inverse-zoom', String((1 / zoom).toFixed(6)));
    root.querySelectorAll('[data-world-node]').forEach(function (node) {
      if (node.dataset.worldFixedScreenIcon === '1') {
        node.style.removeProperty('width');
        node.style.removeProperty('min-width');
        node.style.removeProperty('height');
        node.style.removeProperty('padding');
        node.style.removeProperty('transform');
        delete node.dataset.worldFixedScreenIcon;
      }
      node.classList.remove('is-cluster-hidden');
    });
    root.querySelectorAll('.mg-world-geo-cluster-layer,.mg-world-dot-cluster-layer').forEach(function (layer) {
      layer.replaceChildren();
      layer.hidden = true;
    });
  }

  new MutationObserver(apply).observe(root, { attributes: true, attributeFilter: ['style','data-world-zoom-level','data-world-zoom-tier','data-world-avatar-visibility'] });
  document.addEventListener('mg:world-zoom-change', apply);
  document.addEventListener('mg:world-target-drop-saved', apply);
  window.addEventListener('resize', apply);
  apply();
})(window, document);
