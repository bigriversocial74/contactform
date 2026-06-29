document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-customer-profile-page]');
  if (!root || !window.Microgifter || typeof Microgifter.get !== 'function') return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }
  function qs(selector, scope) { return (scope || root).querySelector(selector); }
  function dateText(value) { var time = Date.parse(value || ''); return time ? new Date(time).toLocaleString() : '—'; }
  function profileUrl() { var params = new URLSearchParams(location.search || ''); params.delete('tab'); return '/api/merchant/customer-profile.php' + (params.toString() ? '?' + params.toString() : ''); }
  function label(value) { return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); }); }

  function score(profile) {
    var c = profile.customer || {}, m = profile.metrics || {};
    var rewards = profile.rewards || [], messages = profile.messages || [], followups = profile.followups || [], notes = profile.notes || [], timeline = profile.timeline || [];
    var value = 20;
    value += Number(m.claimed_rewards || 0) * 14;
    value += Number(m.wallet_rewards_received || 0) * 6;
    value += messages.length ? 12 : 0;
    value += notes.length ? 8 : 0;
    value += followups.some(function (t) { return String(t.status || '') === 'open'; }) ? 8 : 0;
    value += timeline.some(function (t) { return /redeem|claim/i.test(String(t.type || t.title || '')); }) ? 16 : 0;
    if (/inactive|cold/i.test(String(c.status || ''))) value -= 15;
    value = Math.max(0, Math.min(100, Math.round(value)));
    return { value: value, label: value >= 75 ? 'High Intent' : (value >= 50 ? 'Engaged' : (value >= 30 ? 'Warming' : 'Cold')) };
  }

  function rewardState(profile) {
    var rewards = profile.rewards || [];
    if (rewards.some(function (r) { return String(r.status || '').toLowerCase() === 'redeemed'; })) return 'Redeemed';
    if (rewards.some(function (r) { return String(r.status || '').toLowerCase() === 'claimed'; })) return 'Claimed';
    if (rewards.length) return 'Reward Sent';
    return 'No Reward Yet';
  }

  function latest(profile, matcher) {
    var timeline = profile.timeline || [];
    return timeline.find(function (item) { return matcher(String((item.type || '') + ' ' + (item.title || '') + ' ' + (item.body || '')).toLowerCase()); }) || null;
  }

  function suggested(profile, s) {
    var actions = [], tags = ((profile.customer || {}).tags || []).map(function (t) { return String(t).toLowerCase(); });
    var state = rewardState(profile);
    var followups = profile.followups || [];
    if (tags.indexOf('do not message') !== -1) actions.push(['Do not message', 'This contact has a Do Not Message tag. Keep automation paused.', 'blocked']);
    else if (s.value >= 75 && state === 'No Reward Yet') actions.push(['Send reward now', 'High-intent profile with no visible reward sent yet.', 'high']);
    else if (s.value >= 50 && !(profile.messages || []).length) actions.push(['Start CRM message', 'Customer is engaged but has no recent merchant message.', 'medium']);
    if (state === 'Reward Sent') actions.push(['Follow up before reward expires', 'Reward exists, but no claim/redeem result is visible yet.', 'medium']);
    if (state === 'Claimed') actions.push(['Nudge in-store redemption', 'Customer claimed a reward but may still need to redeem it.', 'medium']);
    if (state === 'Redeemed') actions.push(['Ask for feedback or referral', 'Customer completed redemption. Capture feedback while intent is fresh.', 'low']);
    if (followups.some(function (t) { return String(t.status || '') === 'open'; })) actions.push(['Review open follow-up', 'There is already a task attached to this customer.', 'low']);
    if (!actions.length) actions.push(['Keep watching', 'Let more messages, rewards, claims, or Store Canvas events build before taking stronger action.', 'low']);
    return actions.slice(0, 5);
  }

  function resultCards(profile) {
    var rewards = profile.rewards || [], messages = profile.messages || [], redemptions = profile.redemptions || [], followups = profile.followups || [], notes = profile.notes || [];
    var rows = [];
    if (messages.length) rows.push(['Messages', messages.length, 'Last message ' + dateText(messages[0].created_at), 'message']);
    if (rewards.length) rows.push(['Rewards', rewards.length, rewardState(profile), 'reward']);
    if (redemptions.length) rows.push(['Redemptions', redemptions.length, 'Last redemption ' + dateText(redemptions[0].redeemed_at), 'redeem']);
    if (followups.length) rows.push(['Follow-ups', followups.length, followups.filter(function (t) { return String(t.status || '') === 'open'; }).length + ' open', 'task']);
    if (notes.length) rows.push(['Notes', notes.length, 'Latest note saved', 'note']);
    if (!rows.length) rows.push(['CRM Activity', 0, 'No tracked action results yet', 'empty']);
    return rows;
  }

  function movement(profile) {
    var customer = profile.customer || {}, timeline = profile.timeline || [];
    var rows = [];
    rows.push(['First seen', dateText(customer.first_seen_at), 'Customer entered CRM history.']);
    if (customer.source_campaign) rows.push(['Source campaign', customer.source_campaign, 'Original acquisition source.']);
    var trigger = latest(profile, function (text) { return text.indexOf('trigger') !== -1 || text.indexOf('store canvas') !== -1; });
    if (trigger) rows.push(['Store Canvas', dateText(trigger.at), trigger.title || 'Store Canvas activity']);
    var campaign = latest(profile, function (text) { return text.indexOf('campaign') !== -1 || text.indexOf('newsletter') !== -1 || text.indexOf('contest') !== -1; });
    if (campaign) rows.push(['Campaign interaction', dateText(campaign.at), campaign.title || campaign.body || 'Campaign activity']);
    var reward = latest(profile, function (text) { return text.indexOf('reward') !== -1 || text.indexOf('claim') !== -1 || text.indexOf('redeem') !== -1; });
    if (reward) rows.push(['Reward movement', dateText(reward.at), reward.title || reward.body || 'Reward activity']);
    rows.push(['Latest activity', dateText(customer.last_activity_at), 'Most recent CRM update.']);
    return rows;
  }

  function render(profile) {
    if (!profile || root.querySelector('[data-cp-intelligence-ready]')) return;
    var s = score(profile), actions = suggested(profile, s), results = resultCards(profile), move = movement(profile), customer = profile.customer || {};
    var html = '<section class="mg-cp-intelligence" data-cp-intelligence-ready>' +
      '<article class="mg-cp-intel-score"><span>CRM Intelligence Score</span><strong>' + esc(s.value) + '</strong><em>' + esc(s.label) + '</em><p>Computed from rewards, claims, messages, notes, follow-ups, tags, and customer timeline signals.</p></article>' +
      '<article class="mg-cp-intel-card"><header><h3>Suggested Actions</h3><span>' + esc(customer.name || 'Customer') + '</span></header><div class="mg-cp-intel-actions">' + actions.map(function (a) { return '<section class="is-' + esc(a[2]) + '"><strong>' + esc(a[0]) + '</strong><p>' + esc(a[1]) + '</p></section>'; }).join('') + '</div></article>' +
      '<article class="mg-cp-intel-card"><header><h3>Action Results</h3><span>' + esc(rewardState(profile)) + '</span></header><div class="mg-cp-intel-results">' + results.map(function (r) { return '<section><b>' + esc(r[1]) + '</b><div><strong>' + esc(r[0]) + '</strong><p>' + esc(r[2]) + '</p></div></section>'; }).join('') + '</div></article>' +
      '<article class="mg-cp-intel-card mg-cp-intel-wide"><header><h3>Store Canvas / CRM Movement</h3><span>Profile path</span></header><ol class="mg-cp-intel-movement">' + move.map(function (m) { return '<li><b></b><div><strong>' + esc(m[0]) + '</strong><span>' + esc(m[1]) + '</span><p>' + esc(m[2]) + '</p></div></li>'; }).join('') + '</ol></article>' +
      '</section>';
    var target = qs('[data-profile-section="overview"] .mg-cp-center') || qs('[data-profile-section="overview"]') || root;
    target.insertAdjacentHTML('afterbegin', html);
  }

  async function load() {
    try {
      var response = await Microgifter.get(profileUrl());
      render(response.data || response);
    } catch (error) {
      var target = qs('[data-profile-section="overview"] .mg-cp-center') || qs('[data-profile-section="overview"]');
      if (target && !root.querySelector('[data-cp-intelligence-ready]')) target.insertAdjacentHTML('afterbegin', '<section class="mg-cp-intelligence" data-cp-intelligence-ready><article class="mg-cp-intel-card"><header><h3>CRM Intelligence</h3><span>Unavailable</span></header><p>Unable to load intelligence signals for this customer profile.</p></article></section>');
    }
  }

  window.setTimeout(load, 350);
});
