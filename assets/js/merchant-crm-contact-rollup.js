(function () {
  'use strict';

  if (window.__mgMerchantCrmContactRollupInstalled) return;
  window.__mgMerchantCrmContactRollupInstalled = true;

  function number(value) {
    var parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function contactKey(contact) {
    var email = String(contact.email || '').trim().toLowerCase();
    if (email) return 'email:' + email;
    var phone = String(contact.phone || '').replace(/\D+/g, '');
    if (phone) return 'phone:' + phone;
    var name = String(contact.name || '').trim().toLowerCase();
    return name ? 'name:' + name : 'contact:' + String(contact.id || '');
  }

  function latestTimestamp(left, right) {
    var leftTime = Date.parse(left || '') || 0;
    var rightTime = Date.parse(right || '') || 0;
    return rightTime > leftTime ? right : left;
  }

  function resultPriority(value) {
    return ({
      redeemed: 7,
      claimed: 6,
      reward_sent: 5,
      invite_pending: 4,
      email_delivered: 3,
      media_engaged: 2,
      no_action_yet: 1
    })[String(value || '')] || 0;
  }

  function collapseContacts(contacts) {
    var groups = new Map();
    var sumFields = [
      'wallet_count',
      'issued_count',
      'claimed_count',
      'redeemed_count',
      'winner_count',
      'invite_pending_count',
      'emails_queued_count',
      'emails_delivered_count',
      'emails_failed_count'
    ];

    (Array.isArray(contacts) ? contacts : []).forEach(function (contact) {
      var key = contactKey(contact);
      var group = groups.get(key);
      if (!group) {
        var latest = Object.assign({}, contact);
        latest.crm_stats = Object.assign({}, contact.crm_stats || {});
        latest.media_context = Object.assign({}, contact.media_context || {});
        latest.campaign_ids = [];
        latest.campaign_count = 0;
        sumFields.forEach(function (field) { latest[field] = 0; });
        group = { latest: latest, resultStatus: String(contact.result_status || ''), campaigns: new Set() };
        groups.set(key, group);
      }

      var latestContact = group.latest;
      sumFields.forEach(function (field) {
        latestContact[field] += number(contact[field]);
      });

      latestContact.has_account = !!latestContact.has_account || !!contact.has_account;
      latestContact.account_linked = !!latestContact.account_linked || !!contact.account_linked;
      latestContact.account_resolved_by_email = !!latestContact.account_resolved_by_email || !!contact.account_resolved_by_email;
      latestContact.email_verified = !!latestContact.email_verified || !!contact.email_verified;
      latestContact.no_recent_activity = !!latestContact.no_recent_activity && !!contact.no_recent_activity;
      latestContact.last_activity_at = latestTimestamp(latestContact.last_activity_at, contact.last_activity_at);
      latestContact.created_at = latestTimestamp(latestContact.created_at, contact.created_at);
      latestContact.updated_at = latestTimestamp(latestContact.updated_at, contact.updated_at);
      latestContact.crm_score = Math.max(number(latestContact.crm_score), number(contact.crm_score));

      if (resultPriority(contact.result_status) > resultPriority(group.resultStatus)) {
        group.resultStatus = String(contact.result_status || '');
      }

      var campaignId = String(contact.campaign_id || '');
      if (campaignId) group.campaigns.add(campaignId);

      var media = contact.media_context || {};
      latestContact.media_context.progress_percent = Math.max(
        number(latestContact.media_context.progress_percent),
        number(media.progress_percent)
      );
      latestContact.media_context.starts = number(latestContact.media_context.starts) + number(media.starts);
      latestContact.media_context.progress_events = number(latestContact.media_context.progress_events) + number(media.progress_events);
      latestContact.media_context.issued_events = number(latestContact.media_context.issued_events) + number(media.issued_events);
      latestContact.media_context.pppm_handoff = !!latestContact.media_context.pppm_handoff || !!media.pppm_handoff;
    });

    return Array.from(groups.values()).map(function (group) {
      var contact = group.latest;
      var totalSent = number(contact.issued_count) + number(contact.claimed_count) + number(contact.redeemed_count);
      var totalClaimed = number(contact.claimed_count) + number(contact.redeemed_count);
      contact.result_status = group.resultStatus || contact.result_status || 'no_action_yet';
      contact.campaign_ids = Array.from(group.campaigns);
      contact.campaign_count = contact.campaign_ids.length;
      contact.crm_stats = Object.assign({}, contact.crm_stats || {}, {
        score: number(contact.crm_score),
        result_status: contact.result_status,
        inbox: number(contact.wallet_count),
        wallets: number(contact.wallet_count),
        sent: totalSent,
        issued: number(contact.issued_count),
        claimed: totalClaimed,
        redeemed: number(contact.redeemed_count),
        messages: number(contact.emails_delivered_count),
        invite_pending: number(contact.invite_pending_count)
      });
      return contact;
    });
  }

  function totalsFor(contacts, existingTotals) {
    var totals = Object.assign({}, existingTotals || {});
    totals.contacts = contacts.length;
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
    totals.needs_followup = contacts.filter(function (contact) {
      return ['reward_sent', 'invite_pending', 'email_delivered', 'media_engaged'].indexOf(String(contact.result_status || '')) !== -1;
    }).length;
    return totals;
  }

  function transformResponse(response) {
    var hasDataEnvelope = !!(response && response.data && typeof response.data === 'object');
    var payload = hasDataEnvelope ? response.data : response;
    if (!payload || !Array.isArray(payload.contacts)) return response;

    var contacts = collapseContacts(payload.contacts);
    var nextPayload = Object.assign({}, payload, {
      contacts: contacts,
      totals: totalsFor(contacts, payload.totals),
      count: contacts.length,
      contact_rollup: 'merchant_customer'
    });

    return hasDataEnvelope ? Object.assign({}, response, { data: nextPayload }) : nextPayload;
  }

  function install() {
    if (!window.Microgifter || typeof window.Microgifter.get !== 'function') {
      window.setTimeout(install, 10);
      return;
    }
    if (window.Microgifter.get.__mgMerchantCrmContactRollup) return;

    var originalGet = window.Microgifter.get;
    var wrappedGet = function () {
      var args = arguments;
      var url = String(args[0] || '');
      return Promise.resolve(originalGet.apply(this, args)).then(function (response) {
        return url.indexOf('/api/merchant/campaign-contacts.php') !== -1 ? transformResponse(response) : response;
      });
    };
    wrappedGet.__mgMerchantCrmContactRollup = true;
    window.Microgifter.get = wrappedGet;
  }

  install();
})();