document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var host = document.querySelector('[data-media-saved-segments]');
  if (!host) return;

  function enhance() {
    host.querySelectorAll('[data-media-saved-segment]').forEach(function (row) {
      if (row.querySelector('[data-media-action-center-link]')) return;
      var id = row.getAttribute('data-media-saved-segment') || '';
      var actions = row.querySelector('.mg-embed-analytics-actions');
      if (!actions || !id) return;
      var link = document.createElement('a');
      link.className = 'mg-btn mg-btn-primary';
      link.href = '/merchant-crm-segment-action-center.php?segment=' + encodeURIComponent(id);
      link.setAttribute('data-media-action-center-link', '');
      link.textContent = 'Action Center';
      actions.insertBefore(link, actions.firstChild);
    });
  }

  enhance();
  new MutationObserver(enhance).observe(host, { childList: true, subtree: true });
});
