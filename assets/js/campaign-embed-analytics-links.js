document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-command-center]');
  if (!root) return;

  function campaignRefFromRow(row) {
    return row ? (row.getAttribute('data-campaign-row') || row.getAttribute('data-campaign-id') || '') : '';
  }

  function analyticsUrl(ref) {
    return '/merchant-campaign-embed-analytics.php' + (ref ? '?campaign=' + encodeURIComponent(ref) : '');
  }

  function installRowButtons() {
    root.querySelectorAll('[data-campaign-row]').forEach(function (row) {
      if (row.querySelector('[data-campaign-analytics-id]')) return;
      var ref = campaignRefFromRow(row);
      var meta = row.querySelector('.mg-card-meta') || row.querySelector('.mg-action-row') || row;
      var link = document.createElement('a');
      link.className = 'mg-btn mg-btn-ghost';
      link.href = analyticsUrl(ref);
      link.setAttribute('data-campaign-analytics-id', ref);
      link.textContent = 'Analytics';
      meta.appendChild(link);
    });
  }

  installRowButtons();
  var observer = new MutationObserver(installRowButtons);
  observer.observe(root, { childList: true, subtree: true });
});
