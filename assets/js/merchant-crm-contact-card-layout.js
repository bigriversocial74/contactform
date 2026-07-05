document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!document.querySelector('[data-merchant-crm-app]')) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
    });
  }

  function initials(contact) {
    var name = String(contact.name || contact.email || 'C').trim();
    var parts = name.split(/\s+/).filter(Boolean);
    if (parts.length > 1) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 1).toUpperCase();
  }

  function enhanceContactCell(row, contact) {
    if (!row || row.dataset.crmCardReady === '1') return;
    row.dataset.crmCardReady = '1';
    row.classList.add('mg-crm-contact-card-row');
    var cell = row.children[1];
    if (!cell) return;
    var score = cell.querySelector('.mg-crm-contact-score');
    var scoreHtml = score ? score.outerHTML : '';
    if (score) score.remove();
    var name = contact.name || 'Unnamed';
    var email = contact.email || '';
    var note = contact.no_recent_activity ? '<small class="mg-crm-segment-note">No recent activity</small>' : '';
    cell.innerHTML = '<div class="mg-crm-contact-main"><div class="mg-crm-contact-avatar" aria-hidden="true">' + esc(initials(contact)) + '</div><div class="mg-crm-contact-copy"><strong>' + esc(name) + '</strong><small>' + esc(email) + '</small>' + note + '</div></div>';
    if (scoreHtml) row.children[2].insertAdjacentHTML('beforebegin', '<td class="mg-crm-score-cell">' + scoreHtml + '</td>');
  }

  function ensureTableHeader(table) {
    if (!table || table.dataset.crmCardsHeaderReady === '1') return;
    table.dataset.crmCardsHeaderReady = '1';
    var headRow = table.querySelector('thead tr');
    if (!headRow || headRow.children.length >= 8) return;
    var scoreTh = document.createElement('th');
    scoreTh.textContent = 'Score';
    headRow.insertBefore(scoreTh, headRow.children[2] || null);
  }

  function footer(count, total) {
    return '<div class="mg-crm-contact-footer" data-crm-contact-card-footer><span>Showing 1 to ' + esc(count) + ' of ' + esc(total) + ' contacts</span><div class="mg-crm-contact-pager" aria-hidden="true"><button type="button" tabindex="-1">‹</button><span>1</span><button type="button" tabindex="-1">›</button></div></div>';
  }

  function renderFooter(visible, contacts) {
    var wrap = document.querySelector('[data-merchant-crm-table]');
    if (!wrap) return;
    var old = wrap.querySelector('[data-crm-contact-card-footer]');
    if (old) old.remove();
    var count = visible.length || 0;
    var total = contacts.length || count;
    if (count) wrap.insertAdjacentHTML('beforeend', footer(count, total));
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    var contacts = (event.detail && event.detail.contacts) || [];
    var visible = (event.detail && event.detail.visible) || [];
    var table = document.querySelector('[data-merchant-crm-table] .mg-crm-table');
    ensureTableHeader(table);
    visible.forEach(function (contact) {
      var row = document.querySelector('[data-contact-id="' + CSS.escape(String(contact.id || '')) + '"]');
      enhanceContactCell(row, contact);
    });
    renderFooter(visible, contacts);
  });
});
