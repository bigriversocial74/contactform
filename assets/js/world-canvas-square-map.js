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
  addCss('/assets/css/world-canvas-geo-zoom.css', 'data-world-geo-zoom-css');
  addCss('/assets/css/world-canvas-reference-fix.css', 'data-world-reference-fix-css');
  addCss('/assets/css/world-canvas-exact-reference.css', 'data-world-exact-reference-css');
  addCss('/assets/css/world-canvas-merchant-settings.css', 'data-world-merchant-css');
  addCss('/assets/css/world-canvas-hotfix.css', 'data-world-hotfix-css');
  addScript('/assets/js/world-canvas-reference-map.js', 'data-world-reference-map-js');
  addScript('/assets/js/world-canvas-geo-zoom.js', 'data-world-geo-zoom-js');
  addScript('/assets/js/world-canvas-merchant-settings.js', 'data-world-merchant-js');
  addScript('/assets/js/world-canvas-zoom-sync.js', 'data-world-zoom-sync-js');
})(window, document);
