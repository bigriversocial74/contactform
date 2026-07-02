document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  document.querySelectorAll('.mg-agent-chat-sidebar-ad').forEach(function (shell) {
    var placement = shell.querySelector('[data-mg-ad-placement]');
    if (!placement) return;
    function sync() {
      var hasAd = !!placement.querySelector('[data-sponsored-card],[data-ad-campaign-id],.mg-sponsored-card');
      var isEmpty = placement.classList.contains('mg-sponsored-empty') || !hasAd;
      shell.classList.toggle('is-empty', isEmpty);
    }
    sync();
    if ('MutationObserver' in window) {
      new MutationObserver(sync).observe(placement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-sponsored-empty-reason'] });
    }
    window.setTimeout(sync, 800);
    window.setTimeout(sync, 2200);
  });
});
