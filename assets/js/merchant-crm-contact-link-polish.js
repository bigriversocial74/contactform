document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function enhanceContactRows() {
    document.querySelectorAll('.mg-crm-contact-row').forEach(function (row) {
      var avatar = row.querySelector('.mg-crm-contact-avatar');
      if (avatar) avatar.remove();

      var copy = row.querySelector('.mg-crm-contact-copy');
      var nameNode = copy && copy.querySelector(':scope > strong');
      if (!copy || !nameNode || copy.querySelector(':scope > .mg-crm-contact-name-link')) return;

      var actionLink = row.querySelector('[data-crm-view-customer]');
      var href = actionLink && actionLink.getAttribute('href') ? actionLink.getAttribute('href') : '#';
      var link = document.createElement('a');
      link.className = 'mg-crm-contact-name-link';
      link.href = href;
      link.setAttribute('data-crm-view-customer', '');
      link.textContent = nameNode.textContent || 'Unnamed';
      nameNode.replaceWith(link);
    });
  }

  document.addEventListener('mg:crm-contacts:rendered', enhanceContactRows);
  enhanceContactRows();
  window.setTimeout(enhanceContactRows, 250);
  window.setTimeout(enhanceContactRows, 900);
});
