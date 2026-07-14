document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var hero = document.querySelector('[data-crm-desktop-hero]');
  if (!hero) return;

  var latestContacts = [];
  var latestVisible = [];

  function number(value) {
    return Number(value || 0);
  }

  function text(selector, value) {
    var node = hero.querySelector(selector);
    if (node) node.textContent = String(value == null ? 0 : value);
  }

  function percent(part, whole) {
    return whole > 0 ? Math.round((part / whole) * 100) : 0;
  }

  function setWidth(selector, value) {
    var node = hero.querySelector(selector);
    if (node) node.style.width = Math.max(0, Math.min(100, value)) + '%';
  }

  function score(contact) {
    var stats = contact.crm_stats || {};
    return number(contact.crm_score || stats.score);
  }

  function messageCount(contact) {
    var stats = contact.crm_stats || {};
    return number(stats.messages || contact.message_count || contact.emails_delivered_count);
  }

  function claimedCount(contact) {
    return number(contact.claimed_count) + number(contact.redeemed_count);
  }

  function updatePipelineStyles(counts) {
    var total = Math.max(1, counts.new + counts.engaged + counts.nurturing + counts.ready + counts.converted);
    hero.style.setProperty('--pipe-new', Math.max(1, counts.new / total * 100) + 'fr');
    hero.style.setProperty('--pipe-engaged', Math.max(1, counts.engaged / total * 100) + 'fr');
    hero.style.setProperty('--pipe-nurturing', Math.max(1, counts.nurturing / total * 100) + 'fr');
    hero.style.setProperty('--pipe-ready', Math.max(1, counts.ready / total * 100) + 'fr');
    hero.style.setProperty('--pipe-converted', Math.max(1, counts.converted / total * 100) + 'fr');
  }

  function render(contacts) {
    contacts = Array.isArray(contacts) ? contacts : [];
    var total = contacts.length;
    var metrics = contacts.reduce(function (acc, contact) {
      var contactScore = score(contact);
      var status = String(contact.result_status || (contact.crm_stats || {}).result_status || '');
      var messages = messageCount(contact);
      var claimed = claimedCount(contact);
      var issued = number(contact.issued_count || contact.wallet_count);
      var hasActivity = messages > 0 || issued > 0 || claimed > 0 || number(contact.inbox_count) > 0;

      if (contactScore >= 75) acc.high += 1;
      if (['reward_sent', 'invite_pending', 'email_delivered', 'media_engaged'].indexOf(status) !== -1) acc.followup += 1;
      if (claimed > 0) acc.claims += claimed;
      acc.messages += messages;
      if (messages > 0) acc.activeMessages += 1;
      if (contact.email_verified) acc.verified += 1;
      if (!contact.email_verified || !contact.has_account) acc.review += 1;
      if (hasActivity) acc.engaged += 1;
      if (contactScore >= 50) acc.responsive += 1;

      if (claimed > 0) acc.pipeline.converted += 1;
      else if (contactScore >= 75) acc.pipeline.ready += 1;
      else if (issued > 0 || messages > 0) acc.pipeline.nurturing += 1;
      else if (hasActivity || contactScore >= 35) acc.pipeline.engaged += 1;
      else acc.pipeline.new += 1;
      return acc;
    }, {
      high: 0,
      followup: 0,
      claims: 0,
      messages: 0,
      activeMessages: 0,
      verified: 0,
      review: 0,
      engaged: 0,
      responsive: 0,
      pipeline: { new: 0, engaged: 0, nurturing: 0, ready: 0, converted: 0 }
    });

    text('[data-crm-desktop-high]', metrics.high);
    text('[data-crm-desktop-followup]', metrics.followup);
    text('[data-crm-desktop-claims]', metrics.claims);
    text('[data-crm-desktop-messages]', metrics.messages);
    text('[data-crm-desktop-active]', metrics.activeMessages);
    text('[data-crm-desktop-verified]', metrics.verified);
    text('[data-crm-desktop-review]', metrics.review);

    var verifiedPct = percent(metrics.verified, total);
    var engagedPct = percent(metrics.engaged, total);
    var responsivePct = percent(metrics.responsive, total);
    var highPct = percent(metrics.high, total);
    var health = total ? Math.round((verifiedPct * .35) + (engagedPct * .3) + (responsivePct * .2) + ((100 - percent(metrics.review, total)) * .15)) : 0;

    text('[data-crm-health-score]', health);
    text('[data-crm-health-verified]', verifiedPct + '%');
    text('[data-crm-health-engaged]', engagedPct + '%');
    text('[data-crm-health-responsive]', responsivePct + '%');
    text('[data-crm-health-intent]', highPct + '%');
    setWidth('[data-crm-health-bar="verified"]', verifiedPct);
    setWidth('[data-crm-health-bar="engaged"]', engagedPct);
    setWidth('[data-crm-health-bar="responsive"]', responsivePct);
    setWidth('[data-crm-health-bar="intent"]', highPct);

    var ring = hero.querySelector('[data-crm-health-ring]');
    if (ring) ring.style.setProperty('--health', health);

    var healthStatus = hero.querySelector('[data-crm-health-status]');
    if (healthStatus) {
      healthStatus.textContent = health >= 80 ? 'Good' : (health >= 60 ? 'Developing' : 'Needs attention');
      healthStatus.style.color = health >= 80 ? '#0f9f52' : (health >= 60 ? '#c47b00' : '#d64545');
    }

    var stages = metrics.pipeline;
    updatePipelineStyles(stages);
    ['new', 'engaged', 'nurturing', 'ready', 'converted'].forEach(function (key) {
      text('[data-crm-pipeline-' + key + ']', stages[key]);
      text('[data-crm-pipeline-' + key + '-pct]', percent(stages[key], total) + '%');
    });

    var conversionRate = percent(stages.converted, total);
    text('[data-crm-conversion-rate]', conversionRate + '%');
  }

  function csvCell(value) {
    var stringValue = String(value == null ? '' : value);
    return '"' + stringValue.replace(/"/g, '""') + '"';
  }

  function exportCsv() {
    var rows = latestVisible.length ? latestVisible : latestContacts;
    if (!rows.length) return;
    var header = ['Name', 'Email', 'Campaign', 'Campaign Type', 'Account', 'Verified', 'CRM Score', 'Inbox', 'Sent', 'Claimed', 'Messages'];
    var csv = [header.map(csvCell).join(',')];
    rows.forEach(function (contact) {
      var stats = contact.crm_stats || {};
      csv.push([
        contact.name || '',
        contact.email || '',
        contact.campaign_title || '',
        contact.campaign_type || contact.source || '',
        contact.has_account ? 'Yes' : 'No',
        contact.email_verified ? 'Yes' : 'No',
        score(contact),
        stats.inbox || contact.inbox_count || contact.wallet_count || 0,
        stats.sent || stats.issued || contact.issued_count || 0,
        stats.claimed || contact.claimed_count || contact.redeemed_count || 0,
        messageCount(contact)
      ].map(csvCell).join(','));
    });
    var blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = 'microgifter-merchant-crm-contacts.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  function focusDirectory() {
    var search = document.querySelector('[data-crm-mobile-search]');
    var directory = document.querySelector('[data-crm-mobile-directory]');
    if (directory) directory.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (search) window.setTimeout(function () { search.focus(); }, 350);
  }

  document.addEventListener('mg:crm-contacts:rendered', function (event) {
    latestContacts = (event.detail && event.detail.contacts) || [];
    latestVisible = (event.detail && event.detail.visible) || latestContacts;
    render(latestContacts);
  });

  var exportButton = hero.querySelector('[data-crm-desktop-export]');
  if (exportButton) exportButton.addEventListener('click', exportCsv);

  var filterButton = hero.querySelector('[data-crm-desktop-filter]');
  if (filterButton) filterButton.addEventListener('click', focusDirectory);

  var pipelineButton = hero.querySelector('[data-crm-desktop-pipeline]');
  if (pipelineButton) pipelineButton.addEventListener('click', function () {
    var table = document.querySelector('[data-merchant-crm-table]');
    if (table) table.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  var range = hero.querySelector('[data-crm-desktop-range]');
  if (range) range.addEventListener('change', function () {
    hero.setAttribute('data-range-days', String(range.value || '30'));
  });
});
