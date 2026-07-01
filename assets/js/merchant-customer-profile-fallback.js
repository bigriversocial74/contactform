document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-customer-profile-page]');
  if (!root || !window.Microgifter || typeof Microgifter.get !== 'function') return;

  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function set(selector, value) { var node = qs(selector); if (node) node.textContent = value == null || value === '' ? '—' : String(value); }
  function params() { return new URLSearchParams(location.search || ''); }
  function dateText(value) { var time = Date.parse(value || ''); return time ? new Date(time).toLocaleString() : '—'; }
  function idFromUrl() { var p = params(); return p.get('campaign_contact_id') || p.get('contact_id') || p.get('contact') || ''; }
  function profileStillLoading() { return /loading customer/i.test((qs('[data-cp-name]') || {}).textContent || '') || /unable to load customer profile/i.test((qs('.mg-cp-header p') || {}).textContent || ''); }

  function renderFallback(contact) {
    if (!contact) return;
    root.classList.remove('is-loading');
    root.classList.add('is-fallback-profile');
    var header = qs('.mg-cp-header p');
    if (header) header.textContent = 'Loaded from the existing Merchant CRM contact record. Some deep wallet/timeline sections may need the full customer profile API.';
    set('[data-cp-name]', contact.name || contact.email || 'Customer');
    set('[data-cp-email]', contact.email || '');
    set('[data-cp-phone]', contact.phone || '');
    set('[data-cp-initials]', (contact.name || contact.email || 'C').slice(0, 2).toUpperCase());
    set('[data-cp-total-rewards]', contact.wallet_count || 0);
    set('[data-cp-total-claims]', Number(contact.claimed_count || 0) + Number(contact.redeemed_count || 0));
    set('[data-cp-total-tips]', 'USD 0.00');
    set('[data-cp-ltv]', 'Score ' + (contact.crm_score || 0));
    set('[data-cp-location]', '—');
    set('[data-cp-first-seen]', dateText(contact.created_at));
    set('[data-cp-last-activity]', dateText(contact.last_activity_at || contact.updated_at));
    set('[data-cp-source]', contact.campaign_title || contact.source || 'Merchant CRM');
    var pills = qs('[data-cp-pills]');
    if (pills) pills.innerHTML = '<span class="is-blue">' + esc((contact.crm_score_label || 'crm').replace(/_/g, ' ')) + '</span><span class="is-gold">' + esc((contact.result_status || 'contact').replace(/_/g, ' ')) + '</span>';
    var snap = qs('[data-cp-snapshot]');
    if (snap) snap.innerHTML = ['CRM score: ' + (contact.crm_score || 0), 'Next action: ' + (contact.next_best_action || 'Review contact'), 'Wallet items: ' + (contact.wallet_count || 0), 'Claims/redeems: ' + (Number(contact.claimed_count || 0) + Number(contact.redeemed_count || 0))].map(function (item) { return '<li>' + esc(item) + '</li>'; }).join('');
    var profileLink = '/merchant-crm.php?tab=contacts&campaign_contact_id=' + encodeURIComponent(contact.id || idFromUrl());
    var actions = qs('.mg-cp-actions');
    if (actions && !actions.querySelector('[data-cp-open-merchant-crm]')) actions.insertAdjacentHTML('beforeend', '<a class="mg-btn mg-btn-secondary" data-cp-open-merchant-crm href="' + esc(profileLink) + '">Open in Merchant CRM</a>');
    var tables = ['[data-cp-rewards]', '[data-cp-tips]', '[data-cp-sources]', '[data-cp-followups]', '[data-cp-followups-full]', '[data-cp-redemptions]'];
    tables.forEach(function (selector) { var body = qs(selector); if (body) body.innerHTML = '<tr><td colspan="8">Loaded fallback CRM contact. Full detail API did not complete for this request.</td></tr>'; });
  }

  async function fallbackLoad() {
    if (!profileStillLoading()) return;
    var wanted = idFromUrl();
    if (!wanted) return;
    try {
      var response = await Microgifter.get('/api/merchant/campaign-contacts.php');
      var data = response.data || response;
      var contacts = data.contacts || [];
      var contact = contacts.find(function (item) { return String(item.id || '') === String(wanted); });
      if (contact) renderFallback(contact);
    } catch (error) {
      var header = qs('.mg-cp-header p');
      if (header) header.textContent = error.message || 'Unable to load customer profile or fallback CRM contact.';
    }
  }

  window.setTimeout(fallbackLoad, 2400);
});
