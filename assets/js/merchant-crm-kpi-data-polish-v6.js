(function () {
  'use strict';

  function polishCard(card) {
    if (!card || card.dataset.crmKpiPolished === 'true') return;

    var label = card.querySelector('.mg-crm-kpi-label');
    var value = card.querySelector('.mg-crm-kpi-value');
    var meta = card.querySelector('.mg-crm-kpi-meta');
    var chart = card.querySelector('.mg-crm-kpi-chart');

    if (!label || !value || !meta || !chart) return;

    var stack = document.createElement('div');
    stack.className = 'mg-crm-kpi-data-stack';
    stack.appendChild(label);
    stack.appendChild(value);
    stack.appendChild(meta);
    stack.appendChild(chart);

    card.replaceChildren(stack);
    card.dataset.crmKpiPolished = 'true';
  }

  function polishAll(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.mg-crm-desktop-kpis .mg-crm-kpi').forEach(polishCard);
  }

  function start() {
    polishAll(document);

    var hero = document.querySelector('[data-crm-desktop-hero]');
    if (!hero || typeof MutationObserver === 'undefined') return;

    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType !== 1) return;
          if (node.matches && node.matches('.mg-crm-kpi')) polishCard(node);
          else polishAll(node);
        });
      });
    });

    observer.observe(hero, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
