document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-campaign-command-center]');
  if (!root) return;
  var toolbar = root.querySelector('.mg-campaign-toolbar');
  if (toolbar && !toolbar.querySelector('[data-creator-campaign-launcher]')) {
    var link = document.createElement('a');
    link.className = 'mg-btn mg-btn-soft';
    link.href = '/merchant-creator-campaigns.php';
    link.setAttribute('data-creator-campaign-launcher', '');
    link.textContent = 'Creator Campaigns';
    toolbar.appendChild(link);
  }
  var actions = root.querySelector('.mg-campaign-actions .mg-app-panel-body');
  if (actions && !actions.querySelector('[href="/merchant-creator-campaigns.php"]')) {
    var quick = document.createElement('a');
    quick.href = '/merchant-creator-campaigns.php';
    quick.textContent = 'Build a Creator / UGC Campaign';
    actions.insertBefore(quick, actions.firstChild);
  }
});
