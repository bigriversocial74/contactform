document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var contactsById = new Map();

  function customerProfileUrl(row) {
    var contactId = row && row.getAttribute('data-contact-id');
    return contactId ? '/merchant-customer.php?campaign_contact_id=' + encodeURIComponent(contactId) : '#';
  }

  function compactActivity(value) {
    if (!value) return 'Latest activity';
    var parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return 'Latest activity';
    return 'Latest activity · ' + parsed.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: parsed.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
    });
  }

  function moveAccountData(row) {
    var accountCell = row.querySelector('.mg-crm-account-cell');
    var copy = row.querySelector('.mg-crm-contact-copy');
    if (!copy) return;

    var accountLine = copy.querySelector('.mg-crm-contact-account');
    if (!accountLine) {
      accountLine = document.createElement('div');
      accountLine.className = 'mg-crm-contact-account';
      copy.appendChild(accountLine);
    }

    if (accountCell) {
      while (accountCell.firstChild) accountLine.appendChild(accountCell.firstChild);
      accountCell.remove();
    }
  }

  function addLatestActivity(row, contact) {
    var campaignCell = row.querySelector('.mg-crm-campaign-cell');
    if (!campaignCell || !contact) return;

    var existing = campaignCell.querySelector('.mg-crm-latest-activity');
    if (existing) existing.remove();

    var activity = document.createElement('small');
    activity.className = 'mg-crm-latest-activity';
    var campaignCount = Number(contact.campaign_count || 0);
    activity.textContent = compactActivity(contact.last_activity_at) + (campaignCount > 1 ? ' · ' + campaignCount + ' campaigns' : '');

    var rewards = campaignCell.querySelector('.mg-crm-campaign-rewards');
    campaignCell.insertBefore(activity, rewards || null);
  }

  function removeAccountHeader() {
    var table = document.querySelector('.mg-crm-contacts-table');
    var header = table && table.querySelector('thead tr');
    if (!header) return;
    var cells = header.querySelectorAll('th');
    if (cells.length >= 6) cells[3].remove();
  }

  function enhanceContactRows() {
    removeAccountHeader();

    document.querySelectorAll('.mg-crm-contact-row').forEach(function (row) {
      var avatar = row.querySelector('.mg-crm-contact-avatar');
      if (avatar) avatar.remove();

      var score = row.querySelector('.mg-crm-score-line');
      if (score) score.remove();

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
      if (copy && !existingLink && nameNode) {
        var link = document.createElement('a');
        link.className = 'mg-crm-contact-name-link';
        link.href = href;
        link.setAttribute('data-crm-customer-profile-link', '');
        link.textContent = nameNode.textContent || 'Unnamed';
        nameNode.replaceWith(link);
      }

      moveAccountData(row);
      addLatestActivity(row, contactsById.get(String(row.getAttribute('data-contact-id') || '')));
    });
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    contactsById = new Map();
    ((event.detail && event.detail.contacts) || []).forEach(function (contact) {
      contactsById.set(String(contact.id || ''), contact);
    });
    enhanceContactRows();
  });

  enhanceContactRows();
  window.setTimeout(enhanceContactRows, 250);
  window.setTimeout(enhanceContactRows, 900);
});