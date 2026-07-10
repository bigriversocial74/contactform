window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var MG = window.Microgifter;
  var root = document.querySelector('[data-merchant-canvas]');
  if (!root || !MG || typeof MG.post !== 'function') return;

  var blockedRoutes = [
    '/api/merchant-canvas/auto-chat.php',
    '/api/merchant-canvas/campaign-trigger.php',
    '/api/merchant-canvas/campaign-trigger-automation.php'
  ];
  var basePost = MG.post.bind(MG);

  function isBlockedRoute(url) {
    var requestUrl = String(url || '').split('?')[0];
    return blockedRoutes.indexOf(requestUrl) !== -1;
  }

  function containmentError() {
    var error = new Error('Automatic Store Canvas messages and rewards are paused by production containment. Use an explicit manual customer action.');
    error.code = 'merchant_canvas_automatic_actions_disabled';
    error.status = 409;
    return error;
  }

  MG.post = function (url) {
    if (isBlockedRoute(url)) return Promise.reject(containmentError());
    return basePost.apply(MG, arguments);
  };

  MG.merchantCanvasContainment = {
    active: true,
    automaticActionsEnabled: false,
    blockedRoutes: blockedRoutes.slice()
  };

  function enforceContainmentUi() {
    root.classList.add('is-production-contained');
    document.querySelectorAll('[data-canvas-add-trigger], [data-persistent-trigger-button], .mg-canvas-trigger-add-btn').forEach(function (node) {
      node.remove();
    });
    document.querySelectorAll('.mg-canvas-trigger-zone, [data-canvas-persistent-zone]').forEach(function (node) {
      node.remove();
    });
  }

  enforceContainmentUi();
  new MutationObserver(function () {
    window.requestAnimationFrame(enforceContainmentUi);
  }).observe(document.body, { childList: true, subtree: true });

  document.dispatchEvent(new CustomEvent('mg:merchantCanvasContainmentReady', {
    detail: MG.merchantCanvasContainment
  }));
})(window, document);
