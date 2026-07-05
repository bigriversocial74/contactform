document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-merchant-crm-app]');
  if (!root) return;

  var ICONS = {
    view: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.7"/></svg>',
    timeline: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v5l3 2"/><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>',
    message: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6.5h14v10H8.5L5 19.5v-13Z"/></svg>',
    gift: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 10h16v10H4V10Z"/><path d="M12 10v10"/><path d="M4 14h16"/><path d="M7.5 6.5c0-1.4 1-2.5 2.3-2.5 1.7 0 2.2 2 2.2 3.5H9c-.8 0-1.5-.2-1.5-1Z"/><path d="M16.5 6.5c0-1.4-1-2.5-2.3-2.5-1.7 0-2.2 2-2.2 3.5h3c.8 0 1.5-.2 1.5-1Z"/></svg>'
  };

  function buttonConfig(button) {
    if (button.matches('[data-crm-view-customer]')) return { icon: 'view', label: 'View customer' };
    if (button.matches('[data-view-timeline]')) return { icon: 'timeline', label: 'Timeline' };
    if (button.matches('[data-crm-message]')) return { icon: 'message', label: 'Messages' };
    if (button.matches('[data-crm-gift], [data-crm-reward]')) return { icon: 'gift', label: button.closest('tr[data-contact-id]') && button.textContent.toLowerCase().indexOf('invite') !== -1 ? 'Send invite' : 'Send reward' };
    return null;
  }

  function restoreButton(button) {
    if (!button || !button.classList.contains('mg-crm-icon-btn')) return;
    var config = buttonConfig(button);
    if (!config) return;
    button.title = config.label;
    button.setAttribute('aria-label', config.label);
    if (!button.querySelector('svg')) {
      button.innerHTML = ICONS[config.icon] + '<span>' + config.label + '</span>';
    } else {
      var span = button.querySelector('span') || document.createElement('span');
      span.textContent = config.label;
      if (!span.parentNode) button.appendChild(span);
    }
  }

  function restoreRowActions(scope) {
    (scope || document).querySelectorAll('.mg-crm-row-actions .mg-crm-icon-btn').forEach(restoreButton);
  }

  document.addEventListener('mg:crm-contacts:rendered', function () { restoreRowActions(document); });
  document.addEventListener('click', function () { window.setTimeout(function () { restoreRowActions(document); }, 0); }, true);

  var observer = new MutationObserver(function (mutations) {
    var shouldRestore = mutations.some(function (mutation) {
      return mutation.target && mutation.target.closest && mutation.target.closest('.mg-crm-row-actions');
    });
    if (shouldRestore) restoreRowActions(document);
  });
  observer.observe(document.body, { childList: true, characterData: true, subtree: true });

  restoreRowActions(document);
});
