document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-media-performance]');
  if (!root || !window.Microgifter) return;

  var selectedCampaign = root.getAttribute('data-selected-campaign') || '';
  var selectedDays = root.getAttribute('data-selected-days') || '30';

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function pct(value) { return (Number(value || 0)).toFixed(Number(value || 0) % 1 === 0 ? 0 : 1) + '%'; }
  function compactDate(value) { return value ? String(value).replace('T', ' ').replace(/\.\d+Z$/, '') : '—'; }
  function milestones(values) { values = Array.isArray(values) ? values : []; return values.length ? values.map(function (v) { return esc(v) + '%'; }).join(', ') : '—'; }
  function statusBadge(value) { return '<span>' + esc(value || '—') + '</span>'; }
  function setAlert(message, tone) { var node = root.querySelector('[data-media-alert]'); if (!node) return; node.hidden = !message; node.className = 'mg-embed-analytics-alert' + (tone ? ' is-' + tone : ''); node.innerHTML = message || ''; }

  function queryParams() {
    var campaign = root.querySelector('[data-media-campaign-input]');
    var days = root.querySelector('[data-media-days]');
    var params = new URLSearchParams();
    params.set('campaign', campaign && campaign.value ? campaign.value : selectedCampaign);
    params.set('days', days ? days.value : selectedDays);
    return params;
  }

  function apiUrl() { return '/api/merchant/campaign-media-performance.php?' + queryParams().toString(); }
  function pageUrl() { return '/merchant-campaign-media-performance.php?' + queryParams().toString(); }

  function renderStats(totals) {
    var node = root.querySelector('[data-media-stats]');
    if (!node) return;
    totals = totals || {};
    var embed = totals.embed || {};
    var cards = [
      ['Contacts', count(totals.contacts)],
      ['Starts', count(totals.starts)],
      ['Avg / Max Progress', pct(totals.avg_progress_percent) + ' / ' + pct(totals.max_progress_percent)],
      ['Rewards Issued', count(totals.wallet_items)],
      ['Claims / Redeemed', count(totals.claimed) + ' / ' + count(totals.redeemed)],
      ['Embed Loads / Opens', count(embed.loaded) + ' / ' + count(embed.opened)]
    ];
    node.innerHTML = cards.map(function (card) { return '<article><b>' + esc(card[1]) + '</b><span>' + esc(card[0]) + '</span></article>'; }).join('');
  }

  function renderSummary(campaign) {
    var node = root.querySelector('[data-media-summary]');
    if (!node) return;
    campaign = campaign || {};
    var title = root.querySelector('[data-media-title]');
    var desc = root.querySelector('[data-media-description]');
    var analyticsLink = root.querySelector('[data-media-embed-analytics-link]');
    if (title) title.textContent = campaign.title || 'Watch / Listen Performance';
    if (desc) desc.textContent = [campaign.campaign_type_label, campaign.provider_label, campaign.status].filter(Boolean).join(' · ') || 'Review campaign media performance.';
    if (analyticsLink && campaign.embed_analytics_url) analyticsLink.href = campaign.embed_analytics_url;
    node.innerHTML = '<article><b>' + esc(campaign.campaign_type_label || 'Media Reward') + '</b><span>' + esc(campaign.provider_label || '') + '</span><small>' + esc(campaign.track_label || '') + '</small></article>' +
      '<article><b>Reward</b><span>' + esc(campaign.reward_template_title || 'Attached reward') + '</span><small>Status: ' + esc(campaign.status || '—') + '</small></article>' +
      '<article><b>Links</b><span><a href="' + esc(campaign.public_url || '#') + '" target="_blank" rel="noopener">Open public page</a> · <a href="' + esc(campaign.embed_qa_url || '#') + '">Embed QA</a> · <a href="' + esc(campaign.embed_analytics_url || '#') + '">Embed analytics</a></span><small>ID: ' + esc(campaign.id || '') + '</small></article>';
  }

  function renderOrigins(rows, ready) {
    var node = root.querySelector('[data-media-origins]');
    if (!node) return;
    rows = rows || [];
    if (!ready) { node.innerHTML = '<p class="mg-empty-copy">Embed analytics table is not available, so website origin attribution is hidden.</p>'; return; }
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">No website embed origins recorded for this media campaign yet.</p>'; return; }
    node.innerHTML = rows.map(function (row) {
      return '<article><div><strong>' + esc(row.origin_host || 'Unknown origin') + '</strong><span>' + esc(row.embed_mode || 'embed') + '</span></div><b>' + count(row.total) + '</b><small>' + count(row.loaded) + ' loaded · ' + count(row.opened) + ' opened · last seen ' + esc(compactDate(row.last_seen)) + '</small>' + (row.page_url ? '<a href="' + esc(row.page_url) + '" target="_blank" rel="noopener">Open source page</a>' : '') + '</article>';
    }).join('');
  }

  function rewardSummary(rewards) {
    rewards = Array.isArray(rewards) ? rewards : [];
    if (!rewards.length) return 'No reward issued yet';
    return rewards.slice(0, 3).map(function (reward) { return (reward.milestone_percent ? reward.milestone_percent + '% · ' : '') + (reward.title || 'Reward') + ' · ' + (reward.status || 'issued'); }).join('\n');
  }

  function renderContacts(rows) {
    var table = root.querySelector('[data-media-contact-table]');
    if (!table) return;
    rows = rows || [];
    if (!rows.length) {
      table.innerHTML = '<tbody><tr><td><div class="mg-empty-actions"><strong>No contacts yet.</strong><p>Customer progress will appear after someone starts this Watch or Listen campaign.</p><a href="/merchant-campaigns.php">Open Campaigns</a></div></td></tr></tbody>';
      return;
    }
    table.innerHTML = '<thead><tr><th>Contact</th><th>Progress</th><th>Milestones</th><th>Rewards / Inbox</th><th>Attribution</th><th>Last Activity</th></tr></thead><tbody>' + rows.map(function (row) {
      var attribution = row.attribution || {};
      var source = attribution.origin_host || attribution.label || attribution.source || 'Public page';
      var rewardTitle = rewardSummary(row.rewards || []);
      return '<tr><td><strong>' + esc(row.name || 'Customer') + '</strong><small>' + esc(row.email || '') + (row.phone ? ' · ' + esc(row.phone) : '') + '</small></td>' +
        '<td>' + pct(row.max_progress_percent) + '<small>' + count(row.starts) + ' starts · ' + count(row.progress_events) + ' progress events</small></td>' +
        '<td>' + esc(milestones(row.milestones_reached)) + '<small>' + count(row.wallet_items) + ' issued</small></td>' +
        '<td><strong>' + esc(row.inbox_status || '—') + '</strong><small>' + esc(rewardTitle).replace(/\n/g, '<br>') + '</small><small>' + (row.pppm_handoff ? 'PPPM handoff ready' : 'No PPPM handoff yet') + '</small></td>' +
        '<td>' + statusBadge(source) + '<small>' + esc(attribution.embed_mode || attribution.source || 'public_page') + '</small></td>' +
        '<td>' + esc(compactDate(row.last_activity_at)) + '</td></tr>';
    }).join('') + '</tbody>';
  }

  function renderEvents(rows) {
    var node = root.querySelector('[data-media-events]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<div class="mg-empty-actions"><strong>No recent media events.</strong><p>Starts, progress, and issued milestone events will appear here.</p></div>'; return; }
    node.innerHTML = rows.map(function (row) {
      return '<article><b>' + esc(row.event_type || 'event') + '</b><span>' + esc(row.contact_email || 'Unknown contact') + '</span><small>' + pct(row.progress_percent) + (row.milestone_percent ? ' · milestone ' + esc(row.milestone_percent) + '%' : '') + ' · ' + esc(compactDate(row.created_at)) + '</small></article>';
    }).join('');
  }

  function render(data) {
    renderSummary(data.campaign || {});
    renderStats(data.totals || {});
    renderOrigins(data.embed_origins || [], data.embed_analytics_ready !== false);
    renderContacts(data.contacts || []);
    renderEvents(data.recent_events || []);
    setAlert('', '');
    if (window.history) window.history.replaceState({}, '', pageUrl());
  }

  async function load() {
    var params = queryParams();
    if (!params.get('campaign')) { setAlert('<strong>Choose a campaign.</strong> Open this page from the Watch / Listen performance panel or paste a campaign slug/id.', 'warn'); return; }
    setAlert('<strong>Loading media performance...</strong>', 'info');
    try {
      var response = await Microgifter.get(apiUrl());
      var data = response.data || response;
      render(data);
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to load media performance.') + '</strong>', 'warn');
    }
  }

  var form = root.querySelector('[data-media-performance-filters]');
  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); load(); });
  root.addEventListener('change', function (event) { if (event.target && event.target.matches('[data-media-days]')) load(); });
  load();
});
