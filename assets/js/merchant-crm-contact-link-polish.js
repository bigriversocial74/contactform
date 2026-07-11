document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  function customerProfileUrl(row) {
    var contactId = row && row.getAttribute('data-contact-id');
    return contactId ? '/merchant-customer.php?campaign_contact_id=' + encodeURIComponent(contactId) : '#';
  }

  function enhanceContactRows() {
    document.querySelectorAll('.mg-crm-contact-row').forEach(function (row) {
      var avatar = row.querySelector('.mg-crm-contact-avatar');
      if (avatar) avatar.remove();

      var href = customerProfileUrl(row);
      var actionLink = row.querySelector('[data-crm-view-customer]');
      if (actionLink && href !== '#') {
        actionLink.href = href;
        actionLink.removeAttribute('data-crm-view-customer');
        actionLink.setAttribute('data-crm-customer-profile-link', '');
        actionLink.setAttribute('title', 'Open customer profile');
        actionLink.setAttribute('aria-label', 'Open customer profile');
      }

      var copy = row.querySelector('.mg-crm-contact-copy');
      var nameNode = copy && copy.querySelector(':scope > strong');
      var existingLink = copy && copy.querySelector(':scope > .mg-crm-contact-name-link');
      if (!copy || existingLink || !nameNode) return;

      var link = document.createElement('a');
      link.className = 'mg-crm-contact-name-link';
      link.href = href;
      link.setAttribute('data-crm-customer-profile-link', '');
      link.textContent = nameNode.textContent || 'Unnamed';
      nameNode.replaceWith(link);
    });
  }

  document.addEventListener('mg:crm-contacts:rendered', enhanceContactRows);
  enhanceContactRows();
  window.setTimeout(enhanceContactRows, 250);
  window.setTimeout(enhanceContactRows, 900);
});
