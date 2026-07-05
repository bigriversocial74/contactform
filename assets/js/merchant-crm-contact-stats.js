document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!document.querySelector('[data-merchant-crm-app]')) return;

  function set(selector, value) {
    var node = document.querySelector(selector);
    if (node) node.textContent = String(value == null ? 0 : value);
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    var contacts = (event.detail && event.detail.visible) || [];
    var totals = contacts.reduce(function (acc, contact) {
      var stats = contact.crm_stats || {};
      if (Number(contact.crm_score || stats.score || 0) >= 75) acc.high += 1;
      if (['reward_sent', 'invite_pending', 'email_delivered'].indexOf(String(contact.result_status || stats.result_status || '')) !== -1) acc.followup += 1;
      acc.claimed += Number(contact.claimed_count || stats.claimed || 0) + Number(contact.redeemed_count || stats.redeemed || 0);
      return acc;
    }, { high: 0, followup: 0, claimed: 0 });
    set('[data-crm-stat-high]', totals.high);
    set('[data-crm-stat-followup]', totals.followup);
    set('[data-crm-stat-claimed]', totals.claimed);
  });
});
