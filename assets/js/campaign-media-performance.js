document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-media-performance]');
  if (!root || !window.Microgifter) return;

  var selectedCampaign = root.getAttribute('data-selected-campaign') || '';
  var selectedDays = root.getAttribute('data-selected-days') || '30';
  var urlParams = new URLSearchParams(location.search || '');
  var currentData = null;
  var visibleContacts = [];
  var savedSegments = [];

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function pct(value) { return (Number(value || 0)).toFixed(Number(value || 0) % 1 === 0 ? 0 : 1) + '%'; }
  function compactDate(value) { return value ? String(value).replace('T', ' ').replace(/\.\d+Z$/, '') : '—'; }
  function milestones(values) { values = Array.isArray(values) ? values : []; return values.length ? values.map(function (v) { return esc(v) + '%'; }).join(', ') : '—'; }
  function statusBadge(value) { return '<span>' + esc(value || '—') + '</span>'; }
  function setAlert(message, tone) { var node = root.querySelector('[data-media-alert]'); if (!node) return; node.hidden = !message; node.className = 'mg-embed-analytics-alert' + (tone ? ' is-' + tone : ''); node.innerHTML = message || ''; }
  function toast(message) { if (window.Microgifter && Microgifter.toast) Microgifter.toast(message); else setAlert('<strong>' + esc(message) + '</strong>', 'info'); }

  function applyInitialParams() {
    var segment = urlParams.get('segment');
    var q = urlParams.get('q');
    var segmentNode = root.querySelector('[data-media-segment]');
    var searchNode = root.querySelector('[data-media-search]');
    if (segment && segmentNode) segmentNode.value = segment;
    if (q && searchNode) searchNode.value = q;
  }

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
  function selectedSegment() { var node = root.querySelector('[data-media-segment]'); return node && node.value ? node.value : 'all'; }
  function searchTerm() { var node = root.querySelector('[data-media-search]'); return node && node.value ? node.value.trim().toLowerCase() : ''; }
  function rawSearchTerm() { var node = root.querySelector('[data-media-search]'); return node && node.value ? node.value.trim() : ''; }
  function exportUrl(segment) {
    var params = queryParams();
    params.set('segment', segment || selectedSegment());
    return '/api/merchant/campaign-media-performance-export.php?' + params.toString();
  }
  function savedSegmentsUrl(includeCampaign) {
    var params = new URLSearchParams();
    var campaign = queryParams().get('campaign');
    if (includeCampaign && campaign) params.set('campaign', campaign);
    return '/api/merchant/crm-media-segments.php' + (params.toString() ? '?' + params.toString() : '');
  }

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
    var crmLink = root.querySelector('[data-media-crm-campaign]');
    if (title) title.textContent = campaign.title || 'Watch / Listen Performance';
    if (desc) desc.textContent = [campaign.campaign_type_label, campaign.provider_label, campaign.status].filter(Boolean).join(' · ') || 'Review campaign media performance.';
    if (analyticsLink && campaign.embed_analytics_url) analyticsLink.href = campaign.embed_analytics_url;
    if (crmLink && campaign.crm_campaign_url) crmLink.href = campaign.crm_campaign_url;
    node.innerHTML = '<article><b>' + esc(campaign.campaign_type_label || 'Media Reward') + '</b><span>' + esc(campaign.provider_label || '') + '</span><small>' + esc(campaign.track_label || '') + '</small></article>' +
      '<article><b>Reward</b><span>' + esc(campaign.reward_template_title || 'Attached reward') + '</span><small>Status: ' + esc(campaign.status || '—') + '</small></article>' +
      '<article><b>Links</b><span><a href="' + esc(campaign.public_url || '#') + '" target="_blank" rel="noopener">Open public page</a> · <a href="' + esc(campaign.embed_qa_url || '#') + '">Embed QA</a> · <a href="' + esc(campaign.embed_analytics_url || '#') + '">Embed analytics</a> · <a href="' + esc(campaign.crm_campaign_url || '#') + '">CRM campaign</a></span><small>ID: ' + esc(campaign.id || '') + '</small></article>';
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

  function segmentMatches(row, segment) { return !segment || segment === 'all' || row.behavior_bucket === segment; }
  function textMatches(row, term) {
    if (!term) return true;
    var attr = row.attribution || {};
    return [row.name, row.email, row.phone, row.behavior_label, attr.source, attr.origin_host, attr.embed_mode].join(' ').toLowerCase().indexOf(term) !== -1;
  }
  function getFilteredContacts() {
    var contacts = currentData && Array.isArray(currentData.contacts) ? currentData.contacts : [];
    var segment = selectedSegment();
    var term = searchTerm();
    return contacts.filter(function (row) { return segmentMatches(row, segment) && textMatches(row, term); });
  }

  function renderSegmentSummary() {
    var node = root.querySelector('[data-media-segment-summary]');
    if (!node || !currentData) return;
    var totals = currentData.totals || {};
    var buckets = totals.behavior_buckets || {};
    var segment = selectedSegment();
    var label = segment === 'all' ? 'All contacts' : (visibleContacts[0] && visibleContacts[0].behavior_label ? visibleContacts[0].behavior_label : segment.replace(/_/g, ' '));
    var exportNode = root.querySelector('[data-media-export]');
    var exportAllNode = root.querySelector('[data-media-export-all]');
    if (exportNode) exportNode.href = exportUrl(segment);
    if (exportAllNode) exportAllNode.href = exportUrl('all');
    node.innerHTML = '<article><b>' + esc(label) + '</b><span>' + count(visibleContacts.length) + ' visible contacts</span><small>All ' + count(buckets.all || 0) + ' · Started incomplete ' + count(buckets.started_incomplete || 0) + ' · Milestone unclaimed ' + count(buckets.milestone_unclaimed || 0) + ' · Claimed unredeemed ' + count(buckets.claimed_unredeemed || 0) + ' · Redeemed ' + count(buckets.redeemed || 0) + '</small></article>';
  }

  function renderContacts(rows) {
    var table = root.querySelector('[data-media-contact-table]');
    if (!table) return;
    rows = rows || [];
    visibleContacts = rows;
    renderSegmentSummary();
    if (!rows.length) {
      table.innerHTML = '<tbody><tr><td><div class="mg-empty-actions"><strong>No contacts match this filter.</strong><p>Change the behavior filter or search field to review more contacts.</p><a href="/merchant-campaigns.php">Open Campaigns</a></div></td></tr></tbody>';
      return;
    }
    table.innerHTML = '<thead><tr><th>Contact</th><th>Progress</th><th>Milestones</th><th>Rewards / Inbox</th><th>Attribution</th><th>Follow-Up Actions</th><th>Last Activity</th></tr></thead><tbody>' + rows.map(function (row) {
      var attribution = row.attribution || {};
      var actions = row.action_urls || {};
      var source = attribution.origin_host || attribution.label || attribution.source || 'Public page';
      var rewardTitle = rewardSummary(row.rewards || []);
      return '<tr><td><strong>' + esc(row.name || 'Customer') + '</strong><small>' + esc(row.email || '') + (row.phone ? ' · ' + esc(row.phone) : '') + '</small><small>' + esc(row.behavior_label || '') + '</small></td>' +
        '<td>' + pct(row.max_progress_percent) + '<small>' + count(row.starts) + ' starts · ' + count(row.progress_events) + ' progress events</small></td>' +
        '<td>' + esc(milestones(row.milestones_reached)) + '<small>' + count(row.wallet_items) + ' issued</small></td>' +
        '<td><strong>' + esc(row.inbox_status || '—') + '</strong><small>' + esc(rewardTitle).replace(/\n/g, '<br>') + '</small><small>' + (row.pppm_handoff ? 'PPPM handoff ready' : 'No PPPM handoff yet') + '</small></td>' +
        '<td>' + statusBadge(source) + '<small>' + esc(attribution.embed_mode || attribution.source || 'public_page') + '</small></td>' +
        '<td><a href="' + esc(actions.crm_profile || '#') + '">CRM profile</a><a href="' + esc(actions.message || actions.mailto || '#') + '">Message</a><a href="' + esc(actions.send_bonus_reward || '#') + '">Bonus reward</a><a href="' + esc(actions.add_to_segment || '#') + '">Segment</a></td>' +
        '<td>' + esc(compactDate(row.last_activity_at)) + '</td></tr>';
    }).join('') + '</tbody>';
  }

  function applyFilters() { renderContacts(getFilteredContacts()); }

  function renderEvents(rows) {
    var node = root.querySelector('[data-media-events]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<div class="mg-empty-actions"><strong>No recent media events.</strong><p>Starts, progress, and issued milestone events will appear here.</p></div>'; return; }
    node.innerHTML = rows.map(function (row) {
      return '<article><b>' + esc(row.event_type || 'event') + '</b><span>' + esc(row.contact_email || 'Unknown contact') + '</span><small>' + pct(row.progress_percent) + (row.milestone_percent ? ' · milestone ' + esc(row.milestone_percent) + '%' : '') + ' · ' + esc(compactDate(row.created_at)) + '</small></article>';
    }).join('');
  }

  function renderSavedSegments() {
    var node = root.querySelector('[data-media-saved-segments]');
    if (!node) return;
    if (!savedSegments.length) {
      node.innerHTML = '<article><b>No saved media segments yet.</b><span>Choose a behavior/search filter and click Save Segment.</span><small>Segments are dynamic and refresh from campaign activity.</small></article>';
      return;
    }
    node.innerHTML = savedSegments.map(function (segment) {
      return '<article data-media-saved-segment="' + esc(segment.id) + '"><b>' + esc(segment.name) + '</b><span>' + esc(segment.campaign_title || 'Media campaign') + ' · ' + esc(segment.behavior_label || 'All contacts') + ' · ' + count(segment.last_count) + ' contacts</span><small>' + esc(segment.days || 30) + ' days' + (segment.search ? ' · search: ' + esc(segment.search) : '') + ' · refreshed ' + esc(compactDate(segment.last_refreshed_at || segment.updated_at)) + '</small><div class="mg-embed-analytics-actions"><a class="mg-btn mg-btn-primary" href="' + esc(segment.open_url || '#') + '">Open segment</a><a class="mg-btn mg-btn-soft" href="' + esc(segment.export_url || '#') + '">Export CSV</a><a class="mg-btn mg-btn-soft" href="' + esc(segment.crm_url || '#') + '">CRM</a><a class="mg-btn mg-btn-ghost" href="' + esc(segment.message_url || '#') + '">Message</a><a class="mg-btn mg-btn-ghost" href="' + esc(segment.reward_url || '#') + '">Reward</a><button class="mg-btn mg-btn-soft" type="button" data-media-delete-segment="' + esc(segment.id) + '">Delete</button></div></article>';
    }).join('');
  }

  async function loadSavedSegments() {
    var node = root.querySelector('[data-media-saved-segments]');
    if (node) node.innerHTML = '<article><b>Loading saved segments...</b><span>Refreshing dynamic counts.</span></article>';
    try {
      var response = await Microgifter.get(savedSegmentsUrl(false));
      var data = response.data || response;
      savedSegments = data.segments || [];
      if (data.schema_ready === false && node) node.innerHTML = '<article><b>Saved segments SQL is not installed.</b><span>Import database/merchant_crm_media_segments_v1.sql to enable this panel.</span></article>';
      else renderSavedSegments();
    } catch (error) {
      if (node) node.innerHTML = '<article><b>Unable to load saved segments.</b><span>' + esc(error.message || 'Try again after SQL import.') + '</span></article>';
    }
  }

  async function saveCurrentSegment() {
    var params = queryParams();
    if (!params.get('campaign')) return setAlert('<strong>Load a campaign first.</strong>', 'warn');
    var defaultName = (currentData && currentData.campaign && currentData.campaign.title ? currentData.campaign.title + ' · ' : '') + (selectedSegment() === 'all' ? 'All media contacts' : selectedSegment().replace(/_/g, ' '));
    var name = window.prompt('Name this saved CRM media segment:', defaultName);
    if (!name) return;
    setAlert('<strong>Saving segment...</strong>', 'info');
    try {
      await Microgifter.post('/api/merchant/crm-media-segments.php', {
        action: 'save',
        name: name.trim(),
        campaign: params.get('campaign'),
        days: params.get('days') || selectedDays,
        behavior_segment: selectedSegment(),
        search: rawSearchTerm()
      });
      toast('Saved CRM media segment.');
      await loadSavedSegments();
      setAlert('', '');
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to save segment.') + '</strong>', 'warn');
    }
  }

  async function deleteSavedSegment(id) {
    if (!id || !window.confirm('Delete this saved segment?')) return;
    try {
      await Microgifter.post('/api/merchant/crm-media-segments.php', { action: 'delete', segment_id: id });
      toast('Saved segment deleted.');
      await loadSavedSegments();
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to delete segment.') + '</strong>', 'warn');
    }
  }

  function render(data) {
    currentData = data || {};
    renderSummary(currentData.campaign || {});
    renderStats(currentData.totals || {});
    renderOrigins(currentData.embed_origins || [], currentData.embed_analytics_ready !== false);
    renderContacts(getFilteredContacts());
    renderEvents(currentData.recent_events || []);
    setAlert('', '');
    if (window.history) {
      var params = queryParams();
      if (selectedSegment() !== 'all') params.set('segment', selectedSegment());
      if (rawSearchTerm()) params.set('q', rawSearchTerm());
      var saved = urlParams.get('saved_segment');
      if (saved) params.set('saved_segment', saved);
      window.history.replaceState({}, '', '/merchant-campaign-media-performance.php?' + params.toString());
    }
  }

  async function load() {
    var params = queryParams();
    if (!params.get('campaign')) { setAlert('<strong>Choose a campaign.</strong> Open this page from the Watch / Listen performance panel or paste a campaign slug/id.', 'warn'); loadSavedSegments(); return; }
    setAlert('<strong>Loading media performance...</strong>', 'info');
    try {
      var response = await Microgifter.get(apiUrl());
      var data = response.data || response;
      render(data);
      loadSavedSegments();
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to load media performance.') + '</strong>', 'warn');
      loadSavedSegments();
    }
  }

  applyInitialParams();
  var form = root.querySelector('[data-media-performance-filters]');
  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); load(); });
  root.addEventListener('change', function (event) { if (event.target && event.target.matches('[data-media-days]')) load(); if (event.target && event.target.matches('[data-media-segment]')) applyFilters(); });
  root.addEventListener('input', function (event) { if (event.target && event.target.matches('[data-media-search]')) applyFilters(); });
  root.addEventListener('click', function (event) {
    if (event.target && event.target.matches('[data-media-export],[data-media-export-all],[data-media-crm-campaign]') && event.target.getAttribute('href') === '#') {
      event.preventDefault();
      setAlert('<strong>Load a campaign first.</strong>', 'warn');
    }
    if (event.target && event.target.matches('[data-media-save-segment]')) { event.preventDefault(); saveCurrentSegment(); }
    if (event.target && event.target.matches('[data-media-refresh-segments]')) { event.preventDefault(); loadSavedSegments(); }
    if (event.target && event.target.matches('[data-media-delete-segment]')) { event.preventDefault(); deleteSavedSegment(event.target.getAttribute('data-media-delete-segment')); }
  });
  load();
});
