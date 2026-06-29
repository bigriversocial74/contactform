window.Microgifter = window.Microgifter || {};
(function(window, document){
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  function num(value, fallback){
    var n = parseFloat(value);
    return Number.isFinite(n) && n > 0 ? n : fallback;
  }
  function currentZoom(){
    return Math.max(1, num(root.style.getPropertyValue('--mg-world-zoom'), num(root.dataset.worldZoomLevel, 1)));
  }
  function apply(){
    var zoom = currentZoom();
    var inverse = 1 / zoom;
    root.style.setProperty('--mg-world-inverse-zoom', String(inverse.toFixed(5)));
    root.querySelectorAll('[data-world-current-viewer], [data-world-node].is-current-viewer').forEach(function(node){
      node.dataset.worldFixedScreenIcon = '1';
      node.style.setProperty('--mg-world-inverse-zoom', String(inverse.toFixed(5)));
      node.style.width = '28px';
      node.style.minWidth = '28px';
      node.style.height = '28px';
      node.style.padding = '0';
      node.style.transform = 'translate(-50%,-50%) scale(' + inverse.toFixed(5) + ')';
    });
  }
  new MutationObserver(apply).observe(root, {attributes:true, attributeFilter:['style','data-world-zoom-level','data-world-avatar-visibility']});
  document.addEventListener('mg:world-merchant-settings-saved', apply);
  document.addEventListener('mg:world-target-drop-saved', apply);
  window.addEventListener('resize', apply);
  window.setInterval(apply, 350);
  apply();
})(window, document);
