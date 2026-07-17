document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-crm-app]');
  var tableWrap = root && root.querySelector('[data-merchant-crm-table]');
  if (!root || !tableWrap) return;

  var desktopInput = root.querySelector('[data-crm-desktop-search]');
  var mobileInput = root.querySelector('[data-crm-mobile-search]');
  var desktopReset = root.querySelector('[data-crm-desktop-search-reset]');
  var mobileReset = root.querySelector('[data-crm-mobile-search-reset]');
  var mobileClear = root.querySelector('[data-crm-mobile-search-clear]');
  var desktopEmpty = root.querySelector('[data-crm-desktop-search-empty]');
  var mobileEmpty = root.querySelector('[data-crm-mobile-search-empty]');
  var visibleCount = root.querySelector('[data-crm-desktop-visible-count]');
  var state = { query: '', pageSize: 25, visibleLimit: 25, contacts: new Map(), timer: 0 };

  function normalize(value) {
    return String(value == null ? '' : value).toLowerCase().replace(/^@+/, '').replace(/\s+/g, ' ').trim();
  }

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }

  function rows() {
    return Array.prototype.slice.call(tableWrap.querySelectorAll('.mg-crm-contact-row'));
  }

  function ensureFooter() {
    var footer = root.querySelector('[data-crm-directory-pagination]');
    if (footer) return footer;
    footer = document.createElement('div');
    footer.className = 'mg-crm-directory-pagination';
    footer.setAttribute('data-crm-directory-pagination', '');
    footer.hidden = true;
    footer.innerHTML = '<span data-crm-directory-summary aria-live="polite"></span><button type="button" data-crm-directory-more>Show 25 more</button>';
    tableWrap.insertAdjacentElement('afterend', footer);
    return footer;
  }

  function contactForRow(row) {
    return state.contacts.get(String(row.getAttribute('data-contact-id') || '')) || null;
  }

  function addIdentityLine(row, contact) {
    var copy = row.querySelector('.mg-crm-contact-copy');
    if (!copy) return;
    var existing = copy.querySelector('[data-crm-directory-identity]');
    if (existing) existing.remove();
    if (!contact || (!contact.crm_username && !contact.lifecycle_stage && !contact.crm_status)) return;
    var parts = [];
    if (contact.crm_username) parts.push('@' + String(contact.crm_username).replace(/^@+/, ''));
    if (contact.lifecycle_stage) parts.push(String(contact.lifecycle_stage).replace(/_/g, ' '));
    if (contact.crm_status) parts.push(String(contact.crm_status).replace(/_/g, ' '));
    var line = document.createElement('small');
    line.className = 'mg-crm-directory-identity';
    line.setAttribute('data-crm-directory-identity', '');
    line.innerHTML = parts.map(esc).join('<span aria-hidden="true"> · </span>');
    var email = copy.querySelector('small');
    if (email && email.nextSibling) copy.insertBefore(line, email.nextSibling);
    else copy.appendChild(line);
  }

  function decorateRows(contacts) {
    state.contacts.clear();
    (Array.isArray(contacts) ? contacts : []).forEach(function (contact) {
      state.contacts.set(String(contact.id || contact.campaign_contact_id || ''), contact);
    });
    rows().forEach(function (row) {
      var contact = contactForRow(row);
      if (!contact) return;
      row.dataset.crmCanonicalId = String(contact.crm_contact_id || '');
      row.dataset.crmUsername = String(contact.crm_username || '');
      row.dataset.crmName = String(contact.name || '');
      row.dataset.crmPhone = String(contact.phone || '');
      row.dataset.crmStage = String(contact.lifecycle_stage || '');
      row.dataset.crmStatus = String(contact.crm_status || '');
      row.dataset.crmCampaign = String((contact.campaign_titles || []).join(' ') || contact.campaign_title || '');
      row.dataset.crmSource = String((contact.sources || []).join(' ') || contact.source || '');
      row.dataset.crmSearchIndex = normalize(contact.search_index || [
        contact.crm_username, contact.crm_mention, contact.name, contact.email, contact.phone,
        (contact.campaign_titles || []).join(' '), (contact.campaign_types || []).join(' '),
        contact.campaign_title, contact.campaign_type, (contact.sources || []).join(' '), contact.source,
        contact.lifecycle_stage, contact.crm_status, contact.result_status, contact.next_best_action,
        contact.id, contact.crm_contact_id
      ].join(' '));
      addIdentityLine(row, contact);
    });
  }

  function searchableText(row) {
    return normalize(row.dataset.crmSearchIndex || [
      row.dataset.crmUsername, row.dataset.crmName, row.dataset.contactEmail, row.dataset.crmPhone,
      row.dataset.crmCampaign, row.dataset.crmSource, row.dataset.crmStage, row.dataset.crmStatus,
      row.dataset.contactId, row.dataset.crmCanonicalId, row.textContent
    ].join(' '));
  }

  function matches(row, query) {
    if (!query) return true;
    var haystack = searchableText(row);
    return query.split(' ').filter(Boolean).every(function (token) { return haystack.indexOf(token) !== -1; });
  }

  function syncInputs(source) {
    [desktopInput, mobileInput].forEach(function (input) {
      if (input && input !== source && input.value !== state.query) input.value = state.query;
    });
    if (desktopReset) desktopReset.hidden = !state.query;
    if (mobileReset) mobileReset.hidden = !state.query;
  }

  function syncUrl() {
    var url = new URL(window.location.href);
    if (state.query) url.searchParams.set('q', state.query);
    else url.searchParams.delete('q');
    history.replaceState(history.state, '', url.pathname + (url.search ? url.search : '') + url.hash);
  }

  function apply() {
    var list = rows();
    var matched = list.filter(function (row) { return matches(row, state.query); });
    var shown = 0;
    list.forEach(function (row) {
      var match = matched.indexOf(row) !== -1;
      var withinPage = match && shown < state.visibleLimit;
      row.hidden = !withinPage;
      row.classList.toggle('is-search-hidden', !match);
      row.classList.toggle('is-directory-page-hidden', match && !withinPage);
      if (withinPage) shown += 1;
    });

    if (visibleCount) visibleCount.textContent = String(shown);
    if (desktopEmpty) desktopEmpty.hidden = !state.query || matched.length > 0 || list.length === 0;
    if (mobileEmpty) mobileEmpty.hidden = !state.query || matched.length > 0 || list.length === 0;
    tableWrap.classList.toggle('is-mobile-search-empty', !!state.query && matched.length === 0 && list.length > 0);

    var footer = ensureFooter();
    var summary = footer.querySelector('[data-crm-directory-summary]');
    var more = footer.querySelector('[data-crm-directory-more]');
    var remaining = Math.max(0, matched.length - shown);
    footer.hidden = matched.length === 0;
    if (summary) summary.textContent = 'Showing ' + shown + ' of ' + matched.length + (state.query ? ' matching' : '') + ' contacts';
    if (more) {
      more.hidden = remaining === 0;
      more.textContent = 'Show ' + Math.min(state.pageSize, remaining) + ' more';
    }
    root.dataset.crmDirectoryQuery = state.query;
    root.dataset.crmDirectoryMatched = String(matched.length);
    root.dataset.crmDirectoryShown = String(shown);
    document.dispatchEvent(new CustomEvent('mg:crm-directory:filtered', { detail: { query: state.query, matched: matched.length, shown: shown } }));
  }

  function setQuery(value, source, updateUrl) {
    state.query = normalize(value);
    state.visibleLimit = state.pageSize;
    syncInputs(source || null);
    if (updateUrl !== false) syncUrl();
    apply();
  }

  function queueQuery(input) {
    window.clearTimeout(state.timer);
    state.timer = window.setTimeout(function () { setQuery(input.value, input, true); }, 60);
  }

  [desktopInput, mobileInput].forEach(function (input) {
    if (!input) return;
    input.addEventListener('input', function () { queueQuery(input); });
    input.addEventListener('search', function () { setQuery(input.value, input, true); });
  });

  [desktopReset, mobileReset, mobileClear].forEach(function (button) {
    if (!button) return;
    button.addEventListener('click', function () {
      setQuery('', null, true);
      var target = window.matchMedia('(max-width: 720px)').matches ? mobileInput : desktopInput;
      if (target) target.focus();
    });
  });

  root.querySelectorAll('[data-crm-desktop-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
      var target = window.matchMedia('(max-width: 720px)').matches ? mobileInput : desktopInput;
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(function () { target.focus(); }, 250);
      }
    });
  });

  root.addEventListener('click', function (event) {
    var more = event.target.closest('[data-crm-directory-more]');
    if (!more) return;
    state.visibleLimit += state.pageSize;
    apply();
  });

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    var detail = event.detail || {};
    decorateRows(detail.contacts || detail.visible || []);
    state.visibleLimit = state.pageSize;
    window.requestAnimationFrame(apply);
  });

  var params = new URLSearchParams(window.location.search || '');
  state.query = normalize(params.get('q') || params.get('search') || '');
  syncInputs(null);
  ensureFooter();
  apply();

  window.MicrogifterMerchantCrmDirectory = Object.freeze({
    apply: apply,
    getQuery: function () { return state.query; },
    setQuery: function (query) { setQuery(query, null, true); },
    resetPage: function () { state.visibleLimit = state.pageSize; apply(); }
  });
});
