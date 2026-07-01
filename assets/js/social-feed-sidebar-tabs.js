window.Microgifter = window.Microgifter || {};

(function (window, document) {
  'use strict';

  var root = document.querySelector('[data-social-feed]');
  if (!root) return;

  var initialView = String(root.dataset.initialFeedView || 'discover').trim();
  if (!['discover', 'following', 'mine'].includes(initialView) || initialView === 'discover') return;

  function syncInitialView() {
    var target = root.querySelector('[data-feed-tab="' + initialView + '"]');
    if (!target || target.classList.contains('is-active')) return;
    target.click();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncInitialView, { once: true });
  } else {
    syncInitialView();
  }
})(window, document);
