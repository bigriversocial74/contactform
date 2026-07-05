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

  function splitActionsColumn() {
    document.querySelectorAll('.mg-crm-contacts-table').forEach(function (table) {
      var headerRow = table.querySelector('thead tr');
      if (headerRow && !headerRow.querySelector('[data-crm-actions-head]')) {
        var engagementHead = headerRow.querySelector('th:nth-child(5)');
        if (engagementHead) engagementHead.textContent = 'Engagement';
        var th = document.createElement('th');
        th.textContent = 'Actions';
        th.setAttribute('data-crm-actions-head', '1');
        headerRow.appendChild(th);
      }

      table.querySelectorAll('tbody tr[data-contact-id]').forEach(function (row) {
        if (row.querySelector('.mg-crm-actions-cell')) return;
        var engagementCell = row.querySelector('.mg-crm-engagement-cell');
        if (!engagementCell) return;
        var actions = engagementCell.querySelector('.mg-crm-row-actions');
        if (!actions) return;
        var actionCell = document.createElement('td');
        actionCell.className = 'mg-crm-actions-cell';
        actionCell.appendChild(actions);
        engagementCell.insertAdjacentElement('afterend', actionCell);
      });
    });
  }

  function run() {
    cleanScoreLabels();
    splitActionsColumn();
  }

  document.addEventListener('mg:crm-contacts:rendered', run);
  run();
});
