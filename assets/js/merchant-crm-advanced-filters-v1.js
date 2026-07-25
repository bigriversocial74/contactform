document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-merchant-crm-app]');
  var table = root && root.querySelector('[data-merchant-crm-table]');
  var filterButton = root && root.querySelector('[data-crm-desktop-filter]');
  if (!root || !table || !filterButton) return;

  var state = { limit: 25, filters: { stage: '', status: '', account: '', verified: '', campaign: '' }, applying: false, contacts: new Map() };

  function normalize(value) { return String(value == null ? '' : value).toLowerCase().replace(/\s+/g, ' ').trim(); }
  function rows() { return Array.prototype.slice.call(table.querySelectorAll('.mg-crm-contact-row')); }
  function active() { return Object.keys(state.filters).some(function (key) { return !!state.filters[key]; }); }
  function contactText(row) {
    return normalize([row.dataset.crmCampaign, row.dataset.crmCampaignType, row.dataset.crmSource, row.textContent].join(' '));
  }
  function matches(row) {
    if (row.classList.contains('is-search-hidden')) return false;
    if (state.filters.stage && normalize(row.dataset.crmStage) !== state.filters.stage) return false;
    if (state.filters.status && normalize(row.dataset.crmStatus) !== state.filters.status) return false;
    if (state.filters.account === 'linked' && row.dataset.crmHasAccount !== '1') return false;
    if (state.filters.account === 'unlinked' && row.dataset.crmHasAccount !== '0') return false;
    if (state.filters.verified === 'verified' && row.dataset.crmVerified !== '1') return false;
    if (state.filters.verified === 'unverified' && row.dataset.crmVerified !== '0') return false;
    if (state.filters.campaign && contactText(row).indexOf(state.filters.campaign) === -1) return false;
    return true;
  }
  function ensurePanel() {
    var panel = root.querySelector('[data-crm-advanced-filters]');
    if (panel) return panel;
    panel = document.createElement('section');
    panel.className = 'mg-crm-advanced-filters';
    panel.setAttribute('data-crm-advanced-filters', '');
    panel.hidden = true;
    panel.innerHTML =
      '<header><div><strong>Filter CRM contacts</strong><span>Lifecycle, account, verification, campaign, and source filters.</span></div><button type="button" data-crm-filter-close aria-label="Close filters">×</button></header>' +
      '<div class="mg-crm-advanced-filter-grid">' +
        '<label>Lifecycle stage<select data-crm-filter="stage"><option value="">All stages</option><option value="lead">Lead</option><option value="follower">Follower</option><option value="prospect">Prospect</option><option value="supporter">Supporter</option><option value="redeemer">Redeemer</option><option value="customer">Customer</option></select></label>' +
        '<label>CRM status<select data-crm-filter="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></select></label>' +
        '<label>Account<select data-crm-filter="account"><option value="">All contacts</option><option value="linked">Account linked</option><option value="unlinked">No account</option></select></label>' +
        '<label>Verification<select data-crm-filter="verified"><option value="">Any verification</option><option value="verified">Verified</option><option value="unverified">Unverified</option></select></label>' +
        '<label class="is-wide">Campaign or source<input type="search" maxlength="100" placeholder="Campaign title, type, or source" data-crm-filter="campaign"></label>' +
      '</div>' +
      '<footer><span data-crm-filter-summary>No filters applied</span><div><button type="button" data-crm-filter-clear>Clear</button><button type="button" data-crm-filter-more hidden>Show 25 more</button></div></footer>';
    var toolbar = root.querySelector('[data-crm-desktop-directory]');
    if (toolbar) toolbar.insertAdjacentElement('afterend', panel);
    return panel;
  }
  function syncUrl() {
    var url = new URL(window.location.href);
    Object.keys(state.filters).forEach(function (key) {
      if (state.filters[key]) url.searchParams.set(key, state.filters[key]); else url.searchParams.delete(key);
    });
    history.replaceState(history.state, '', url.pathname + (url.search ? url.search : '') + url.hash);
  }
  function syncPanel(matched, shown) {
    var panel = ensurePanel();
    panel.querySelectorAll('[data-crm-filter]').forEach(function (control) {
      var key = control.getAttribute('data-crm-filter') || '';
      if (Object.prototype.hasOwnProperty.call(state.filters, key) && control.value !== state.filters[key]) control.value = state.filters[key];
    });
    var count = Object.keys(state.filters).filter(function (key) { return !!state.filters[key]; }).length;
    var summary = panel.querySelector('[data-crm-filter-summary]');
    if (summary) summary.textContent = count ? ('Showing ' + shown + ' of ' + matched + ' contacts · ' + count + ' filter' + (count === 1 ? '' : 's')) : 'No filters applied';
    var more = panel.querySelector('[data-crm-filter-more]');
    if (more) {
      var remaining = Math.max(0, matched - shown);
      more.hidden = remaining === 0;
      more.textContent = 'Show ' + Math.min(25, remaining) + ' more';
    }
    filterButton.classList.toggle('is-active', count > 0);
    filterButton.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
  }
  function apply() {
    if (state.applying) return;
    state.applying = true;
    window.requestAnimationFrame(function () {
      var list = rows();
      if (!active()) {
        list.forEach(function (row) {
          row.classList.remove('is-advanced-filter-hidden', 'is-advanced-page-hidden');
        });
        var directory = window.MicrogifterMerchantCrmDirectory;
        if (directory && typeof directory.apply === 'function') directory.apply();
        syncPanel(list.length, list.filter(function (row) { return !row.hidden; }).length);
        state.applying = false;
        return;
      }

      list.forEach(function (row) { row.classList.remove('is-directory-page-hidden'); });
      var matched = list.filter(matches);
      var set = new Set(matched);
      var shown = 0;
      list.forEach(function (row) {
        var match = set.has(row);
        var visible = match && shown < state.limit;
        row.hidden = !visible;
        row.classList.toggle('is-advanced-filter-hidden', !match);
        row.classList.toggle('is-advanced-page-hidden', match && !visible);
        if (visible) shown++;
      });
      var visibleCount = root.querySelector('[data-crm-desktop-visible-count]');
      if (visibleCount) visibleCount.textContent = String(shown);
      var pagination = root.querySelector('[data-crm-directory-pagination]');
      if (pagination) pagination.hidden = true;
      syncPanel(matched.length, shown);
      document.dispatchEvent(new CustomEvent('mg:crm-advanced-filtered', { detail: { matched: matched.length, shown: shown, filters: Object.assign({}, state.filters) } }));
      state.applying = false;
    });
  }
  function setFilter(key, value) {
    if (!Object.prototype.hasOwnProperty.call(state.filters, key)) return;
    state.filters[key] = normalize(value);
    state.limit = 25;
    syncUrl();
    apply();
  }
  function clear() {
    Object.keys(state.filters).forEach(function (key) { state.filters[key] = ''; });
    state.limit = 25;
    syncUrl();
    syncPanel(0, 0);
    apply();
  }

  filterButton.addEventListener('click', function (event) {
    event.preventDefault();
    event.stopImmediatePropagation();
    var panel = ensurePanel();
    panel.hidden = !panel.hidden;
    syncPanel(rows().length, rows().filter(function (row) { return !row.hidden; }).length);
    if (!panel.hidden) {
      var first = panel.querySelector('[data-crm-filter]');
      if (first) first.focus();
    }
  }, true);

  root.addEventListener('click', function (event) {
    if (event.target.closest('[data-crm-filter-close]')) {
      ensurePanel().hidden = true;
      syncPanel(0, 0);
      return;
    }
    if (event.target.closest('[data-crm-filter-clear]')) { clear(); return; }
    if (event.target.closest('[data-crm-filter-more]')) { state.limit += 25; apply(); }
  });
  root.addEventListener('change', function (event) {
    var control = event.target.closest('[data-crm-filter]');
    if (control) setFilter(control.getAttribute('data-crm-filter') || '', control.value);
  });
  root.addEventListener('input', function (event) {
    var control = event.target.closest('input[data-crm-filter]');
    if (!control) return;
    window.clearTimeout(control._crmFilterTimer);
    control._crmFilterTimer = window.setTimeout(function () {
      setFilter(control.getAttribute('data-crm-filter') || '', control.value);
    }, 100);
  });

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    state.contacts.clear();
    ((event.detail && event.detail.contacts) || []).forEach(function (contact) {
      state.contacts.set(String(contact.id || contact.campaign_contact_id || ''), contact);
    });
    rows().forEach(function (row) {
      var contact = state.contacts.get(String(row.getAttribute('data-contact-id') || '')) || {};
      row.dataset.crmHasAccount = contact.has_account ? '1' : '0';
      row.dataset.crmVerified = contact.email_verified ? '1' : '0';
      row.dataset.crmCampaignType = String((contact.campaign_types || []).join(' ') || contact.campaign_type || '');
      row.dataset.crmSource = String((contact.sources || []).join(' ') || contact.source || '');
    });
    window.requestAnimationFrame(function () { window.requestAnimationFrame(apply); });
  });
  document.addEventListener('mg:crm-directory:filtered', function () {
    if (active()) apply();
  });

  var params = new URLSearchParams(window.location.search || '');
  Object.keys(state.filters).forEach(function (key) { state.filters[key] = normalize(params.get(key) || ''); });
  ensurePanel();
  syncPanel(0, 0);
  if (active()) apply();
});
