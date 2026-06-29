window.Microgifter = window.Microgifter || {};
(function (window, document) {
  'use strict';
  var root = document.querySelector('[data-world-canvas]');
  if (!root) return;
  function addCss(href, key) {
    if (document.querySelector('link[' + key + ']')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    link.setAttribute(key, '1');
    document.head.appendChild(link);
  }
  function addScript(src, key) {
    if (document.querySelector('script[' + key + ']')) return;
    var script = document.createElement('script');
    script.src = src;
    script.defer = true;
    script.setAttribute(key, '1');
    document.body.appendChild(script);
  }
  var state = document.querySelector('[data-world-state]');
  if (state) state.textContent = 'Geo network map: zoomed out shows stable clusters; zooming in spreads clusters into live user and merchant avatars.';
  addCss('/assets/css/world-canvas-geo-zoom.css', 'data-world-geo-zoom-css');
  addCss('/assets/css/world-canvas-reference-fix.css', 'data-world-reference-fix-css');
  addCss('/assets/css/world-canvas-exact-reference.css', 'data-world-exact-reference-css');
  addScript('/assets/js/world-canvas-reference-map.js', 'data-world-reference-map-js');
  addScript('/assets/js/world-canvas-geo-zoom.js', 'data-world-geo-zoom-js');
})(window, document);
