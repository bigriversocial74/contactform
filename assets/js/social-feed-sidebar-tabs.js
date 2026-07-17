window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed]');
  if (!root) return;

  var initialView = String(root.dataset.initialFeedView || 'discover').trim();
  if (!['discover', 'following', 'mine'].includes(initialView) || initialView === 'discover') return;

  function activateInitialView() {
    var target = root.querySelector('[data-feed-tab="' + initialView + '"]');
    var loading = root.querySelector('[data-feed-loading]');
    if (!target || target.classList.contains('is-active')) return;

    function feedIsSettled() {
      return !loading || loading.hidden || loading.classList.contains('mg-hidden');
    }

    function activate() {
      if (!target.classList.contains('is-active')) target.click();
    }

    if (feedIsSettled()) {
      activate();
      return;
    }

    if ('MutationObserver' in window) {
      var observer = new MutationObserver(function () {
        if (!feedIsSettled()) return;
        observer.disconnect();
        activate();
      });
      observer.observe(loading, { attributes: true, attributeFilter: ['class', 'hidden'] });
      window.setTimeout(function () {
        observer.disconnect();
        activate();
      }, 5000);
      return;
    }

    window.setTimeout(activate, 400);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', activateInitialView, { once: true });
  } else {
    activateInitialView();
  }
})(window, document);