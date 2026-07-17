(function () {
  'use strict';

  if (window.__mgMerchantCrmDirectoryDataInstalled) return;
  window.__mgMerchantCrmDirectoryDataInstalled = true;

  var canonicalCache = { loadedAt: 0, promise: null, contacts: [] };

  function number(value) {
    var parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function normalize(value) {
    return String(value == null ? '' : value).toLowerCase().replace(/^@+/, '').replace(/\s+/g, ' ').trim();
  }

  function emailKey(value) {
    var email = normalize(value);
    return email && email.indexOf('@') > 0 ? email : '';
  }

  function phoneKey(value) {
    var phone = String(value == null ? '' : value).replace(/\D+/g, '');
    return phone.length >= 7 ? phone : '';
  }

  function contactKey(contact) {
    var email = emailKey(contact.email);
    if (email) return 'email:' + email;
    var phone = phoneKey(contact.phone);
    if (phone) return 'phone:' + phone;
    var name = normalize(contact.name);
    return name ? 'name:' + name : 'contact:' + String(contact.id || '');
  }

  function timestamp(value) {
    return Date.parse(String(value || '').replace(' ', 'T')) || 0;
  }

  function latestTimestamp(left, right) {
    return timestamp(right) > timestamp(left) ? right : left;
  }

  function resultPriority(value) {
    return ({ redeemed: 7, claimed: 6, reward_sent: 5, invite_pending: 4, email_delivered: 3, media_engaged: 2, no_action_yet: 1 })[String(value || '')] || 0;
  }

  function collapseContacts(contacts) {
    var groups = new Map();
    var sumFields = ['wallet_count', 'issued_count', 'claimed_count', 'redeemed_count', 'winner_count', 'invite_pending_count', 'emails_queued_count', 'emails_delivered_count', 'emails_failed_count'];

    (Array.isArray(contacts) ? contacts : []).forEach(function (contact) {
      var key = contactKey(contact);
      var group = groups.get(key);
      if (!group) {
        var latest = Object.assign({}, contact);
        latest.crm_stats = Object.assign({}, contact.crm_stats || {});
        latest.media_context = Object.assign({}, contact.media_context || {}, { progress_percent: 0, starts: 0, progress_events: 0, issued_events: 0, pppm_handoff: false });
        latest.campaign_ids = [];
        latest.campaign_titles = [];
        latest.campaign_types = [];
        latest.sources = [];
        latest.campaign_count = 0;
        sumFields.forEach(function (field) { latest[field] = 0; });
        group = { latest: latest, resultStatus: String(contact.result_status || ''), campaigns: new Set(), titles: new Set(), types: new Set(), sources: new Set() };
        groups.set(key, group);
      }

      var latestContact = group.latest;
      sumFields.forEach(function (field) { latestContact[field] += number(contact[field]); });
      latestContact.has_account = !!latestContact.has_account || !!contact.has_account;
      latestContact.account_linked = !!latestContact.account_linked || !!contact.account_linked;
      latestContact.account_resolved_by_email = !!latestContact.account_resolved_by_email || !!contact.account_resolved_by_email;
      latestContact.email_verified = !!latestContact.email_verified || !!contact.email_verified;
      latestContact.no_recent_activity = !!latestContact.no_recent_activity && !!contact.no_recent_activity;
      latestContact.last_activity_at = latestTimestamp(latestContact.last_activity_at, contact.last_activity_at);
      latestContact.updated_at = latestTimestamp(latestContact.updated_at, contact.updated_at);
      latestContact.crm_score = Math.max(number(latestContact.crm_score), number(contact.crm_score));

      if (resultPriority(contact.result_status) > resultPriority(group.resultStatus)) group.resultStatus = String(contact.result_status || '');
      if (contact.campaign_id) group.campaigns.add(String(contact.campaign_id));
      if (contact.campaign_title) group.titles.add(String(contact.campaign_title));
      if (contact.campaign_type) group.types.add(String(contact.campaign_type));
      if (contact.source) group.sources.add(String(contact.source));

      var media = contact.media_context || {};
      latestContact.media_context.progress_percent = Math.max(number(latestContact.media_context.progress_percent), number(media.progress_percent));
      latestContact.media_context.starts += number(media.starts);
      latestContact.media_context.progress_events += number(media.progress_events);
      latestContact.media_context.issued_events += number(media.issued_events);
      latestContact.media_context.pppm_handoff = !!latestContact.media_context.pppm_handoff || !!media.pppm_handoff;
    });

    return Array.from(groups.values()).map(function (group) {
      var contact = group.latest;
      var totalSent = number(contact.issued_count) + number(contact.claimed_count) + number(contact.redeemed_count);
      var totalClaimed = number(contact.claimed_count) + number(contact.redeemed_count);
      contact.result_status = group.resultStatus || contact.result_status || 'no_action_yet';
      contact.campaign_ids = Array.from(group.campaigns);
      contact.campaign_titles = Array.from(group.titles);
      contact.campaign_types = Array.from(group.types);
      contact.sources = Array.from(group.sources);
      contact.campaign_count = contact.campaign_ids.length;
      contact.crm_stats = Object.assign({}, contact.crm_stats || {}, {
        score: number(contact.crm_score), result_status: contact.result_status, inbox: number(contact.wallet_count), wallets: number(contact.wallet_count), sent: totalSent,
        issued: number(contact.issued_count), claimed: totalClaimed, redeemed: number(contact.redeemed_count), messages: number(contact.emails_delivered_count), invite_pending: number(contact.invite_pending_count)
      });
      return contact;
    });
  }

  function canonicalMaps(contacts) {
    var maps = { byEmail: new Map(), byPhone: new Map() };
    (Array.isArray(contacts) ? contacts : []).forEach(function (contact) {
      var email = emailKey(contact.email);
      var phone = phoneKey(contact.phone);
      if (email && !maps.byEmail.has(email)) maps.byEmail.set(email, contact);
      if (phone && !maps.byPhone.has(phone)) maps.byPhone.set(phone, contact);
    });
    return maps;
  }

  function mergeCanonical(contact, maps) {
    var canonical = maps.byEmail.get(emailKey(contact.email)) || maps.byPhone.get(phoneKey(contact.phone)) || null;
    contact.campaign_engagement_score = number(contact.crm_score);
    contact.campaign_engagement_label = String(contact.crm_score_label || '');
    contact.directory_contract_version = 1;
    contact.campaign_contact_id = String(contact.id || '');

    if (canonical) {
      contact.crm_contact_id = String(canonical.crm_contact_id || canonical.id || '');
      contact.crm_username = String(canonical.crm_username || canonical.username || '');
      contact.crm_mention = String(canonical.crm_mention || canonical.mention || (contact.crm_username ? '@' + contact.crm_username : ''));
      contact.lifecycle_stage = String(canonical.lifecycle_stage || canonical.stage || 'lead');
      contact.crm_status = String(canonical.crm_status || canonical.status || 'active');
      contact.crm_score = number(canonical.crm_score || canonical.score || contact.crm_score);
      contact.crm_score_label = String(canonical.crm_score_label || canonical.score_label || contact.crm_score_label || '');
      contact.next_best_action = String(canonical.next_best_action || contact.next_best_action || '');
      contact.customer_profile_url = String(canonical.profile_url || contact.customer_profile_url || '');
      contact.crm_contact_url = contact.customer_profile_url || contact.crm_contact_url;
      contact.canonical_contact = {
        id: contact.crm_contact_id,
        username: contact.crm_username,
        mention: contact.crm_mention,
        stage: contact.lifecycle_stage,
        status: contact.crm_status,
        score: contact.crm_score,
        score_label: contact.crm_score_label,
        profile_url: contact.customer_profile_url
      };
    } else {
      contact.crm_contact_id = '';
      contact.crm_username = '';
      contact.crm_mention = '';
      contact.lifecycle_stage = 'lead';
      contact.crm_status = 'active';
      contact.canonical_contact = null;
    }

    contact.search_index = normalize([
      contact.crm_username, contact.crm_mention, contact.name, contact.email, contact.phone,
      (contact.campaign_titles || []).join(' '), (contact.campaign_types || []).join(' '), (contact.sources || []).join(' '),
      contact.campaign_title, contact.campaign_type, contact.source, contact.lifecycle_stage, contact.crm_status,
      contact.result_status, contact.next_best_action, contact.campaign_contact_id, contact.crm_contact_id
    ].join(' '));
    contact.crm_stats = Object.assign({}, contact.crm_stats || {}, {
      score: contact.crm_score,
      score_label: contact.crm_score_label,
      next_best_action: contact.next_best_action
    });
    return contact;
  }

  function totalsFor(contacts, existingTotals, directoryTotal) {
    var totals = Object.assign({}, existingTotals || {});
    totals.contacts = contacts.length;
    totals.directory_contacts = number(directoryTotal || contacts.length);
    totals.accounts = contacts.filter(function (contact) { return !!contact.has_account; }).length;
    totals.no_accounts = contacts.length - totals.accounts;
    totals.verified = contacts.filter(function (contact) { return !!contact.email_verified; }).length;
    totals.wallets = contacts.reduce(function (sum, contact) { return sum + number(contact.wallet_count); }, 0);
    totals.reward_issued = contacts.filter(function (contact) { return number(contact.crm_stats && contact.crm_stats.sent) > 0; }).length;
    totals.reward_claimed = contacts.filter(function (contact) { return number(contact.crm_stats && contact.crm_stats.claimed) > 0; }).length;
    totals.invite_pending = contacts.filter(function (contact) { return number(contact.invite_pending_count) > 0; }).length;
    totals.no_recent_activity = contacts.filter(function (contact) { return !!contact.no_recent_activity; }).length;
    totals.emails_queued = contacts.reduce(function (sum, contact) { return sum + number(contact.emails_queued_count); }, 0);
    totals.emails_delivered = contacts.reduce(function (sum, contact) { return sum + number(contact.emails_delivered_count); }, 0);
    totals.emails_failed = contacts.reduce(function (sum, contact) { return sum + number(contact.emails_failed_count); }, 0);
    totals.high_intent = contacts.filter(function (contact) { return number(contact.crm_score) >= 75; }).length;
    totals.needs_followup = contacts.filter(function (contact) { return ['reward_sent', 'invite_pending', 'email_delivered', 'media_engaged'].indexOf(String(contact.result_status || '')) !== -1; }).length;
    return totals;
  }

  function canonicalContacts(originalGet) {
    if (canonicalCache.promise && Date.now() - canonicalCache.loadedAt < 30000) return canonicalCache.promise;
    canonicalCache.loadedAt = Date.now();
    canonicalCache.promise = Promise.resolve(originalGet.call(window.Microgifter, '/api/merchant/merchant-crm.php?limit=250')).then(function (response) {
      var payload = response && response.data ? response.data : response;
      canonicalCache.contacts = payload && Array.isArray(payload.contacts) ? payload.contacts : [];
      canonicalCache.total = Number(payload && payload.total || canonicalCache.contacts.length);
      return canonicalCache.contacts;
    }).catch(function () {
      canonicalCache.contacts = [];
      canonicalCache.total = 0;
      return [];
    });
    return canonicalCache.promise;
  }

  function transformResponse(response, canonical) {
    var hasEnvelope = !!(response && response.data && typeof response.data === 'object');
    var payload = hasEnvelope ? response.data : response;
    if (!payload || !Array.isArray(payload.contacts)) return response;
    var maps = canonicalMaps(canonical);
    var contacts = collapseContacts(payload.contacts).map(function (contact) { return mergeCanonical(contact, maps); });
    var nextPayload = Object.assign({}, payload, {
      contacts: contacts,
      totals: totalsFor(contacts, payload.totals, canonicalCache.total),
      count: contacts.length,
      contact_rollup: 'canonical_merchant_customer',
      directory_contract_version: 1
    });
    return hasEnvelope ? Object.assign({}, response, { data: nextPayload }) : nextPayload;
  }

  function install() {
    if (!window.Microgifter || typeof window.Microgifter.get !== 'function') {
      window.setTimeout(install, 10);
      return;
    }
    if (window.Microgifter.get.__mgMerchantCrmDirectoryData) return;
    var originalGet = window.Microgifter.get;
    var wrappedGet = function () {
      var args = arguments;
      var url = String(args[0] || '');
      var responsePromise = Promise.resolve(originalGet.apply(this, args));
      if (url.indexOf('/api/merchant/campaign-contacts.php') === -1) return responsePromise;
      return Promise.all([responsePromise, canonicalContacts(originalGet)]).then(function (values) {
        return transformResponse(values[0], values[1]);
      });
    };
    wrappedGet.__mgMerchantCrmDirectoryData = true;
    wrappedGet.__mgMerchantCrmOriginalGet = originalGet;
    window.Microgifter.get = wrappedGet;
  }

  window.MicrogifterMerchantCrmDirectoryData = Object.freeze({
    collapseContacts: collapseContacts,
    mergeCanonical: mergeCanonical,
    invalidate: function () { canonicalCache.loadedAt = 0; canonicalCache.promise = null; canonicalCache.contacts = []; canonicalCache.total = 0; }
  });

  install();
})();
