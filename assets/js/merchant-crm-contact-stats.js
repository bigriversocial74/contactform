document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  if (!document.querySelector('[data-merchant-crm-app]')) return;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
    });
  }

  function label(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); });
  }

  function scoreClass(score) {
    score = Number(score || 0);
    if (score >= 75) return 'is-high';
    if (score >= 50) return 'is-medium';
    if (score >= 30) return 'is-warm';
    return 'is-low';
  }

  function metric(value, labelText) {
    return '<span><strong>' + esc(value) + '</strong>' + esc(labelText) + '</span>';
  }

  function enhanceRow(row, contact) {
    if (!row || !contact || row.dataset.crmStatsReady === '1') return;
    row.dataset.crmStatsReady = '1';
    row.classList.add('has-crm-stats');
    var nameCell = row.children[1];
    var actionCell = row.children[row.children.length - 1];
    var stats = contact.crm_stats || {};
    var score = Number(contact.crm_score || stats.score || 0);
    var result = contact.result_status || stats.result_status || 'no_action_yet';
    var next = contact.next_best_action || stats.next_best_action || 'Review contact';
    if (nameCell) {
      nameCell.insertAdjacentHTML('beforeend', '<div class="mg-crm-contact-score ' + scoreClass(score) + '"><b>' + esc(score) + '</b><span>' + esc(label(contact.crm_score_label || stats.score_label || 'score')) + '</span><small>' + esc(label(result)) + '</small></div>');
    }
    if (actionCell) {
      actionCell.insertAdjacentHTML('afterbegin', '<div class="mg-crm-contact-insights"><div>' +
        metric(stats.issued || contact.issued_count || 0, 'Issued') +
        metric(stats.claimed || contact.claimed_count || 0, 'Claimed') +
        metric(stats.redeemed || contact.redeemed_count || 0, 'Redeemed') +
        metric(stats.messages || contact.emails_delivered_count || 0, 'Messages') +
      '</div><p><strong>Next:</strong> ' + esc(next) + '</p></div>');
    }
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    var contacts = (event.detail && event.detail.visible) || [];
    contacts.forEach(function (contact) {
      var row = document.querySelector('[data-contact-id="' + CSS.escape(String(contact.id || '')) + '"]');
      enhanceRow(row, contact);
    });
    var app = document.querySelector('[data-merchant-crm-app]');
    if (!app || app.dataset.crmStatsSummaryReady === '1') return;
    app.dataset.crmStatsSummaryReady = '1';
    var totals = contacts.reduce(function (acc, contact) {
      if (Number(contact.crm_score || 0) >= 75) acc.high += 1;
      if (['reward_sent', 'invite_pending', 'email_delivered'].indexOf(String(contact.result_status || '')) !== -1) acc.followup += 1;
      acc.claimed += Number(contact.claimed_count || 0) + Number(contact.redeemed_count || 0);
      return acc;
    }, { high: 0, followup: 0, claimed: 0 });
    var target = document.querySelector('.mg-crm-controls, [data-merchant-crm-table]');
    if (target) target.insertAdjacentHTML('beforebegin', '<section class="mg-crm-contact-stat-strip"><article><span>High Intent</span><strong>' + esc(totals.high) + '</strong></article><article><span>Needs Follow-Up</span><strong>' + esc(totals.followup) + '</strong></article><article><span>Claims / Redeems</span><strong>' + esc(totals.claimed) + '</strong></article></section>');
  });
});
