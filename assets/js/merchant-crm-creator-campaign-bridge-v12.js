(function () {
  'use strict';
  if (window.__mgMerchantCrmCreatorCampaignBridgeV12) return;
  window.__mgMerchantCrmCreatorCampaignBridgeV12 = true;
  var cache = { promise: null, loadedAt: 0, contacts: [] };

  function number(value) { var n = Number(value || 0); return Number.isFinite(n) ? n : 0; }
  function normalize(value) { return String(value == null ? '' : value).toLowerCase().replace(/\s+/g, ' ').trim(); }
  function emailKey(value) { var email = normalize(value); return email.indexOf('@') > 0 ? email : ''; }
  function phoneKey(value) { var phone = String(value == null ? '' : value).replace(/\D+/g, ''); return phone.length >= 7 ? phone : ''; }
  function canonicalId(contact) { return String(contact.crm_contact_id || contact.id || ''); }

  function directoryContacts(originalGet) {
    if (cache.promise && Date.now() - cache.loadedAt < 30000) return cache.promise;
    cache.loadedAt = Date.now();
    cache.promise = Promise.resolve(originalGet.call(window.Microgifter, '/api/merchant/merchant-crm.php?limit=250')).then(function (response) {
      var payload = response && response.data ? response.data : response;
      cache.contacts = payload && Array.isArray(payload.contacts) ? payload.contacts : [];
      return cache.contacts;
    }).catch(function () { cache.contacts = []; return []; });
    return cache.promise;
  }

  function synthetic(contact) {
    var relationships = Array.isArray(contact.creator_campaign_relationships) ? contact.creator_campaign_relationships : [];
    var primary = relationships[0] || {};
    var id = canonicalId(contact);
    return {
      id: 'crm:' + id,
      crm_contact_id: id,
      campaign_contact_id: '',
      canonical_only: true,
      creator_campaign_contact: true,
      creator_campaign_relationships: relationships,
      creator_campaign_count: number(contact.creator_campaign_count),
      creator_campaign_relationship_type: String(contact.creator_campaign_relationship_type || primary.relationship_type || ''),
      creator_campaign_relationship_label: String(contact.creator_campaign_relationship_label || ''),
      creator_campaign_title: String(contact.creator_campaign_title || primary.campaign_title || ''),
      creator_campaign_url: String(contact.creator_campaign_url || primary.campaign_url || ''),
      name: String(contact.name || contact.display_name || contact.email || 'Creator partner'),
      email: String(contact.email || ''),
      phone: String(contact.phone || ''),
      has_account: !!contact.has_account,
      account_linked: !!contact.has_account,
      email_verified: !!contact.email_verified,
      crm_username: String(contact.crm_username || contact.username || ''),
      crm_mention: String(contact.crm_mention || contact.mention || ''),
      lifecycle_stage: String(contact.lifecycle_stage || contact.stage || 'custom'),
      crm_status: String(contact.crm_status || contact.status || 'active'),
      crm_score: number(contact.crm_score || contact.score),
      crm_score_label: String(contact.crm_score_label || contact.score_label || ''),
      next_best_action: String(contact.next_best_action || 'Review Creator Campaign relationship'),
      customer_profile_url: String(contact.profile_url || ''),
      crm_contact_url: String(contact.profile_url || ''),
      crm_timeline_url: String(contact.profile_url || ''),
      campaign_id: '',
      campaign_title: String(contact.creator_campaign_title || primary.campaign_title || 'Creator Campaign'),
      campaign_type: 'creator_campaign',
      source: 'creator_campaign_crm',
      campaign_ids: [],
      campaign_titles: [String(contact.creator_campaign_title || primary.campaign_title || 'Creator Campaign')],
      campaign_types: ['creator_campaign'],
      sources: ['creator_campaign_crm'],
      campaign_count: number(contact.creator_campaign_count) || 1,
      result_status: 'creator_campaign_relationship',
      wallet_count: 0, issued_count: 0, claimed_count: 0, redeemed_count: 0, winner_count: 0,
      invite_pending_count: 0, emails_queued_count: 0, emails_delivered_count: 0, emails_failed_count: 0,
      media_context: { is_media_campaign: false },
      crm_stats: { score: number(contact.crm_score || contact.score), score_label: String(contact.crm_score_label || contact.score_label || ''), result_status: 'creator_campaign_relationship', inbox: 0, sent: 0, claimed: 0, messages: 0 },
      search_index: normalize([contact.search_index,contact.creator_campaign_title,(contact.creator_campaign_titles || []).join(' '),(contact.creator_campaign_relationship_types || []).join(' '),'creator campaign creator partner'].join(' ')),
      last_activity_at: primary.last_event_at || contact.last_activity_at || null,
      updated_at: primary.last_event_at || contact.last_activity_at || null
    };
  }

  function appendCanonicalOnly(response, canonical) {
    var hasEnvelope = !!(response && response.data && typeof response.data === 'object');
    var payload = hasEnvelope ? response.data : response;
    if (!payload || !Array.isArray(payload.contacts)) return response;
    var contacts = payload.contacts.slice();
    var ids = new Set(); var emails = new Set(); var phones = new Set();
    contacts.forEach(function (contact) {
      var id = canonicalId(contact); if (id) ids.add(id);
      var email = emailKey(contact.email); if (email) emails.add(email);
      var phone = phoneKey(contact.phone); if (phone) phones.add(phone);
    });
    canonical.forEach(function (contact) {
      if (!number(contact.creator_campaign_count)) return;
      var id = canonicalId(contact); var email = emailKey(contact.email); var phone = phoneKey(contact.phone);
      if ((id && ids.has(id)) || (email && emails.has(email)) || (phone && phones.has(phone))) return;
      contacts.push(synthetic(contact));
      if (id) ids.add(id); if (email) emails.add(email); if (phone) phones.add(phone);
    });
    var totals = Object.assign({}, payload.totals || {});
    totals.contacts = contacts.length;
    totals.creator_campaign_contacts = canonical.filter(function (contact) { return number(contact.creator_campaign_count) > 0; }).length;
    var next = Object.assign({}, payload, { contacts: contacts, count: contacts.length, totals: totals, creator_campaign_contact_bridge: 'canonical_v12' });
    return hasEnvelope ? Object.assign({}, response, { data: next }) : next;
  }

  function install() {
    if (!window.Microgifter || typeof window.Microgifter.get !== 'function') { window.setTimeout(install, 10); return; }
    if (window.Microgifter.get.__mgCreatorCampaignCrmV12) return;
    var originalGet = window.Microgifter.get;
    var wrapped = function () {
      var args = arguments; var url = String(args[0] || '');
      var response = Promise.resolve(originalGet.apply(this, args));
      if (url.indexOf('/api/merchant/campaign-contacts.php') === -1) return response;
      return Promise.all([response,directoryContacts(originalGet)]).then(function (values) { return appendCanonicalOnly(values[0],values[1]); });
    };
    wrapped.__mgCreatorCampaignCrmV12 = true;
    wrapped.__mgCreatorCampaignCrmOriginalGet = originalGet;
    window.Microgifter.get = wrapped;
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    var contacts = (event.detail && event.detail.contacts) || [];
    var map = new Map(); contacts.forEach(function (contact) { map.set(String(contact.id || ''),contact); });
    document.querySelectorAll('[data-merchant-crm-table] .mg-crm-contact-row').forEach(function (row) {
      var contact = map.get(String(row.getAttribute('data-contact-id') || ''));
      if (!contact || !contact.creator_campaign_contact) return;
      row.classList.add('is-creator-campaign-contact');
      var campaignCell = row.querySelector('.mg-crm-campaign-cell');
      if (campaignCell && !campaignCell.querySelector('[data-creator-campaign-relation]')) {
        campaignCell.insertAdjacentHTML('beforeend','<span class="mg-crm-creator-campaign-chip" data-creator-campaign-relation>' + String(contact.creator_campaign_relationship_label || 'Creator Campaign') + '</span>');
      }
      if (contact.canonical_only) {
        row.querySelectorAll('[data-view-timeline],[data-crm-message],[data-crm-gift]').forEach(function (button) { button.disabled = true; button.hidden = true; });
        var actions = row.querySelector('.mg-crm-row-actions');
        if (actions && contact.creator_campaign_url && !actions.querySelector('[data-open-creator-campaign]')) {
          var link = document.createElement('a'); link.className='mg-crm-icon-btn'; link.href=contact.creator_campaign_url; link.setAttribute('data-open-creator-campaign',''); link.textContent='Campaign'; actions.appendChild(link);
        }
      }
    });
  });

  window.MicrogifterMerchantCrmCreatorCampaignBridgeV12 = Object.freeze({ invalidate: function () { cache.promise=null; cache.loadedAt=0; cache.contacts=[]; } });
  install();
})();
