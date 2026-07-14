(function () {
  'use strict';

  function cleanMerchantCrmAnalytics() {
    var hero = document.querySelector('[data-crm-desktop-hero]');
    if (!hero) return;

    hero.querySelectorAll('.mg-crm-kpi-icon, .mg-crm-trends, .mg-crm-desktop-view-pipeline').forEach(function (node) {
      node.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cleanMerchantCrmAnalytics, { once: true });
  } else {
    cleanMerchantCrmAnalytics();
  }

  window.addEventListener('load', cleanMerchantCrmAnalytics, { once: true });

  var observer = new MutationObserver(function () {
    cleanMerchantCrmAnalytics();
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
})();
