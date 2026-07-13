document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-crm-app]');
  if (!root) return;

  var toggle = root.querySelector('[data-crm-mobile-overview-toggle]');
  var body = root.querySelector('[data-crm-mobile-overview-body]');
  var search = root.querySelector('[data-crm-mobile-search]');
  var reset = root.querySelector('[data-crm-mobile-search-reset]');
  var clear = root.querySelector('[data-crm-mobile-search-clear]');
  var empty = root.querySelector('[data-crm-mobile-search-empty]');
  var tableWrap = root.querySelector('[data-merchant-crm-table]');
  var mobile = window.matchMedia('(max-width: 720px)');
  var timer = 0;

  function normalize(value) {
    return String(value == null ? '' : value)
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  function setAccordion(open) {
    if (!toggle || !body) return;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  }

  function syncViewport(event) {
    var matches = event && typeof event.matches === 'boolean' ? event.matches : mobile.matches;
    if (!matches && body) body.hidden = false;
  }

  function searchableText(row) {
    return normalize([
      row.getAttribute('data-contact-email') || '',
      row.textContent || ''
    ].join(' '));
  }

  function applySearch() {
    var query = normalize(search && search.value);
    var rows = Array.prototype.slice.call(root.querySelectorAll('.mg-crm-contact-row'));
    var visible = 0;

    rows.forEach(function (row) {
      var match = !query || searchableText(row).indexOf(query) !== -1;
      row.hidden = !match;
      if (match) visible += 1;
    });

    if (reset) reset.hidden = query === '';
    if (empty) empty.hidden = query === '' || visible > 0 || rows.length === 0;
    if (tableWrap) tableWrap.classList.toggle('is-mobile-search-empty', query !== '' && visible === 0 && rows.length > 0);
  }

  function clearSearch() {
    if (!search) return;
    search.value = '';
    applySearch();
    search.focus();
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      setAccordion(toggle.getAttribute('aria-expanded') !== 'true');
    });
  }

  if (search) {
    search.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(applySearch, 70);
    });
  }

  if (reset) reset.addEventListener('click', clearSearch);
  if (clear) clear.addEventListener('click', clearSearch);

  document.addEventListener('click', function (event) {
    if (event.target.closest('[data-crm-duplicates-open]')) setAccordion(true);
  });

  document.addEventListener('mg:crm-contacts:rendered', function () {
    window.requestAnimationFrame(applySearch);
  });

  if (tableWrap && 'MutationObserver' in window) {
    new MutationObserver(function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(applySearch, 40);
    }).observe(tableWrap, { childList: true, subtree: true });
  }

  if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', syncViewport);
  else if (typeof mobile.addListener === 'function') mobile.addListener(syncViewport);

  setAccordion(true);
  syncViewport();
  applySearch();
});
