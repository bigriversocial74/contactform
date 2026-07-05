document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!document.querySelector('[data-merchant-crm-app]')) return;

  var ICONS = {
    view: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>',
    timeline: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v5l3 2"/><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>',
    message: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6.5h14v10H8.5L5 19.5v-13Z"/></svg>',
    gift: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16v10H4V10Z"/><path d="M12 10v10"/><path d="M4 14h16"/><path d="M7.5 6.5c0-1.4 1-2.5 2.3-2.5 1.7 0 2.2 2 2.2 3.5H9c-.8 0-1.5-.2-1.5-1Z"/><path d="M16.5 6.5c0-1.4-1-2.5-2.3-2.5-1.7 0-2.2 2-2.2 3.5h3c.8 0 1.5-.2 1.5-1Z"/></svg>'
  };

  function qs(selector, root) { return (root || document).querySelector(selector); }
  function qsa(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  function setIcon(button, key, label) {
    if (!button || button.dataset.crmSvgReady === '1') return;
    button.dataset.crmSvgReady = '1';
    button.classList.add('mg-crm-svg-action');
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
    button.innerHTML = ICONS[key] + '<span>' + label + '</span>';
  }

  function labelMetrics(row) {
    var metrics = qsa('.mg-crm-activity-cell .mg-crm-contact-insights span', row);
    var labels = ['Inbox', 'Sent', 'Claimed', 'Messages'];
    metrics.slice(0, 4).forEach(function (metric, index) {
      if (metric.dataset.crmMetricReady === '1') return;
      metric.dataset.crmMetricReady = '1';
      metric.insertAdjacentHTML('afterbegin', '<em>' + labels[index] + '</em>');
    });
  }

  function upgradeRow(row) {
    if (!row) return;
    labelMetrics(row);
    setIcon(qs('[data-crm-view-customer]', row), 'view', 'View customer');
    setIcon(qs('[data-view-timeline]', row), 'timeline', 'Timeline');
    setIcon(qs('[data-crm-message]', row), 'message', 'Messages');
    setIcon(qs('[data-crm-gift], [data-crm-reward]', row), 'gift', 'Send reward');
  }

  function upgradeRows() {
    qsa('[data-merchant-crm-table] tr[data-contact-id]').forEach(upgradeRow);
  }

  document.addEventListener('mg:crm-contacts:rendered', function () {
    window.requestAnimationFrame(upgradeRows);
  });

  upgradeRows();
});
