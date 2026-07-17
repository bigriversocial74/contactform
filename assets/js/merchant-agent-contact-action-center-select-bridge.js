document.addEventListener('click', function (event) {
  'use strict';
  var button = event.target.closest('[data-agent-crm-select-contact]');
  if (!button || !window.MicrogifterMerchantContactActionCenter) return;
  var row = button.closest('[data-agent-crm-contact-row]');
  var nameNode = row && row.querySelector('.mg-agent-crm-result-contact strong');
  window.MicrogifterMerchantContactActionCenter.select({
    id: button.getAttribute('data-contact-id') || '',
    mention: button.getAttribute('data-contact-mention') || '',
    name: nameNode ? nameNode.textContent.trim() : 'CRM contact'
  });
}, true);
