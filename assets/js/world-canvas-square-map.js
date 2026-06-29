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
  addCss('/assets/css/world-canvas-target-drops.css', 'data-world-target-drops-css');
  addCss('/assets/css/world-canvas-target-hotfix.css', 'data-world-target-hotfix-css');
  addCss('/assets/css/world-canvas-target-drops-phase15.css', 'data-world-target-drops-phase15-css');
  addCss('/assets/css/world-canvas-target-drops-phase2.css', 'data-world-target-drops-phase2-css');
  addCss('/assets/css/world-canvas-target-drops-phase3.css', 'data-world-target-drops-phase3-css');
  addCss('/assets/css/world-canvas-test-launch.css', 'data-world-test-launch-css');
  addCss('/assets/css/world-canvas-intercept-tools.css', 'data-world-intercept-tools-css');
  addScript('/assets/js/world-canvas-reference-map.js', 'data-world-reference-map-js');
  addScript('/assets/js/world-canvas-geo-zoom.js', 'data-world-geo-zoom-js');
  addScript('/assets/js/world-canvas-merchant-settings.js', 'data-world-merchant-js');
  addScript('/assets/js/world-canvas-zoom-sync.js', 'data-world-zoom-sync-js');
  addScript('/assets/js/world-canvas-test-launch.js', 'data-world-test-launch-js');
  addScript('/assets/js/world-canvas-target-drops.js', 'data-world-target-drops-js');
  addScript('/assets/js/world-canvas-zone-scale.js', 'data-world-zone-scale-js');
  addScript('/assets/js/world-canvas-run-hooks.js', 'data-world-run-hooks-js');
  addScript('/assets/js/world-canvas-intercept-tools.js', 'data-world-intercept-tools-js');
})(window, document);
