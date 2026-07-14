(function () {
  'use strict';

  function removeDesktopKpiIcons(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.mg-crm-desktop-kpis .mg-crm-kpi-icon').forEach(function (icon) {
      icon.remove();
    });
  }

  function start() {
    removeDesktopKpiIcons(document);

    var hero = document.querySelector('.mg-crm-desktop-hero');
    if (!hero || typeof MutationObserver === 'undefined') return;

    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches('.mg-crm-kpi-icon')) node.remove();
          else removeDesktopKpiIcons(node);
        });
      });
    });

    observer.observe(hero, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
})();
