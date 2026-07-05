document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!document.querySelector('[data-merchant-crm-app]')) return;

  function cleanScoreLabels() {
    document.querySelectorAll('.mg-crm-contact-score em').forEach(function (node) {
      var text = String(node.textContent || '').replace(/reward\s*sent/ig, '').replace(/\s+/g, ' ').trim();
      node.textContent = text;
      node.hidden = !text;
    });
  }

  document.addEventListener('mg:crm-contacts:rendered', cleanScoreLabels);
  cleanScoreLabels();
});
