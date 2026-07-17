document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var input = document.querySelector('[data-crm-desktop-search]');
  var reset = document.querySelector('[data-crm-desktop-search-reset]');
  var empty = document.querySelector('[data-crm-desktop-search-empty]');
  var visibleCount = document.querySelector('[data-crm-desktop-visible-count]');
  var toolbar = document.querySelector('[data-crm-desktop-directory]');
  if (!input || !toolbar) return;

  function normalize(value) {
    return String(value || '').toLowerCase().replace(/^@+/, '').replace(/\s+/g, ' ').trim();
  }

  function rows() {
    return Array.prototype.slice.call(document.querySelectorAll('.mg-crm-contact-row'));
  }

  function searchableText(row) {
    return normalize([
      row.textContent,
      row.getAttribute('data-contact-email'),
      row.getAttribute('data-contact-id')
    ].join(' '));
  }

  function applySearch() {
    var query = normalize(input.value);
    var list = rows();
    var shown = 0;

    list.forEach(function (row) {
      var match = !query || searchableText(row).indexOf(query) !== -1;
      row.hidden = !match;
      row.classList.toggle('is-search-hidden', !match);
      if (match) shown += 1;
    });

    if (visibleCount) visibleCount.textContent = String(shown);
    if (reset) reset.hidden = !query;
    if (empty) empty.hidden = !query || shown > 0 || list.length === 0;
  }

  function focusSearch() {
    toolbar.scrollIntoView({ behavior: 'smooth', block: 'center' });
    window.setTimeout(function () { input.focus(); }, 250);
  }

  input.addEventListener('input', applySearch);
  input.addEventListener('search', applySearch);

  if (reset) {
    reset.addEventListener('click', function () {
      input.value = '';
      applySearch();
      input.focus();
    });
  }

  document.querySelectorAll('[data-crm-desktop-filter]').forEach(function (button) {
    button.addEventListener('click', focusSearch);
  });

  document.addEventListener('mg:crm-contacts:rendered', function () {
    window.requestAnimationFrame(applySearch);
  });

  var table = document.querySelector('[data-merchant-crm-table]');
  if (table && window.MutationObserver) {
    new MutationObserver(function () { applySearch(); }).observe(table, { childList: true, subtree: true });
  }

  var params = new URLSearchParams(window.location.search || '');
  var initialQuery = normalize(params.get('search') || params.get('q') || '');
  if (initialQuery) input.value = initialQuery;
  applySearch();
});
