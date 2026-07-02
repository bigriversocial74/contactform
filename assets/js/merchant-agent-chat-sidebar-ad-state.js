document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  document.querySelectorAll('.mg-agent-chat-sidebar-ad').forEach(function (shell) {
    var placement = shell.querySelector('[data-mg-ad-placement]');
    if (!placement) return;
    function sync() {
      var hasAd = !!placement.querySelector('[data-sponsored-card],[data-ad-campaign-id],.mg-sponsored-card');
      var isLoading = placement.getAttribute('aria-busy') === 'true';
      var isEmpty = hasAd ? false : (placement.classList.contains('mg-sponsored-empty') || !isLoading);
      shell.classList.toggle('is-empty', isEmpty);
      shell.toggleAttribute('data-has-active-ad', hasAd);
    }
    sync();
    if ('MutationObserver' in window) {
      new MutationObserver(sync).observe(placement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'aria-busy', 'data-sponsored-empty-reason'] });
    }
    window.setTimeout(sync, 800);
    window.setTimeout(sync, 2200);
  });
});
