document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-campaign-embed-analytics]');
  if (!root || !window.Microgifter) return;

  var selectedCampaign = root.getAttribute('data-selected-campaign') || '';
  var selectedDays = root.getAttribute('data-selected-days') || '30';

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function pct(value) { return (Number(value || 0)).toFixed(Number(value || 0) % 1 === 0 ? 0 : 2) + '%'; }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function typeLabel(value) { return String(value || 'event').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
  function campaignRef(row) { return row && (row.slug || row.id) ? (row.slug || row.id) : ''; }
  function setAlert(message, tone) { var node = root.querySelector('[data-embed-analytics-alert]'); if (!node) return; node.hidden = !message; node.className = 'mg-embed-analytics-alert' + (tone ? ' is-' + tone : ''); node.innerHTML = message || ''; }

  function queryParams() {
    var campaign = root.querySelector('[data-embed-analytics-campaign]');
    var days = root.querySelector('[data-embed-analytics-days]');
    var params = new URLSearchParams();
    params.set('days', days ? days.value : selectedDays);
    if (campaign && campaign.value) params.set('campaign', campaign.value);
    return params;
  }

  function apiUrl() { return '/api/merchant/campaign-embed-analytics.php?' + queryParams().toString(); }
  function exportUrl(dataset) { var params = queryParams(); params.set('dataset', dataset || 'campaigns'); return '/api/merchant/campaign-embed-analytics-export.php?' + params.toString(); }
  function analyticsUrl() { return window.location.origin + '/merchant-campaign-embed-analytics.php?' + queryParams().toString(); }

  function updateExportLinks() {
    root.querySelectorAll('[data-export-analytics]').forEach(function (link) {
      link.href = exportUrl(link.getAttribute('data-export-analytics') || 'campaigns');
    });
  }

  function renderCampaignPicker(campaigns) {
    var select = root.querySelector('[data-embed-analytics-campaign]');
    if (!select) return;
    var value = select.value || selectedCampaign;
    select.innerHTML = '<option value="">All campaigns</option>' + (campaigns || []).map(function (campaign) {
      var ref = campaign.slug || campaign.id;
      return '<option value="' + esc(ref) + '"' + (ref === value ? ' selected' : '') + '>' + esc(campaign.title || 'Campaign') + ' · ' + esc(campaign.status || '') + '</option>';
    }).join('');
    updateExportLinks();
  }

  function renderStats(totals) {
    var node = root.querySelector('[data-embed-analytics-stats]');
    if (!node) return;
    totals = totals || {};
    var cards = [
      ['Loaded', count(totals.loaded)],
      ['Opened', count(totals.opened) + ' / ' + pct(totals.open_rate)],
      ['Submitted', count(totals.submitted)],
      ['Conversion', pct(totals.conversion_rate)],
      ['Errors', count(totals.error) + ' / ' + pct(totals.error_rate)]
    ];
    node.innerHTML = cards.map(function (card) { return '<article><b>' + esc(card[1]) + '</b><span>' + esc(card[0]) + '</span></article>'; }).join('');
  }

  function renderTimeline(rows) {
    var node = root.querySelector('[data-embed-analytics-timeline]');
    if (!node) return;
    rows = rows || [];
    var maxValue = rows.reduce(function (max, row) { return Math.max(max, Number(row.loaded || 0), Number(row.submitted || 0), Number(row.error || 0)); }, 1);
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">No embed events yet.</p>'; return; }
    node.innerHTML = rows.map(function (row) {
      var loadedWidth = Math.max(3, Math.round((Number(row.loaded || 0) / maxValue) * 100));
      var submittedWidth = Math.max(Number(row.submitted || 0) > 0 ? 3 : 0, Math.round((Number(row.submitted || 0) / maxValue) * 100));
      var errorsWidth = Math.max(Number(row.error || 0) > 0 ? 3 : 0, Math.round((Number(row.error || 0) / maxValue) * 100));
      return '<div class="mg-embed-timeline-row"><time>' + esc(row.date) + '</time><div><span class="is-loaded" style="width:' + loadedWidth + '%"></span><span class="is-submitted" style="width:' + submittedWidth + '%"></span><span class="is-error" style="width:' + errorsWidth + '%"></span></div><small>' + count(row.loaded) + ' loads · ' + count(row.submitted) + ' submits</small></div>';
    }).join('');
  }

  function renderOrigins(rows) {
    var node = root.querySelector('[data-embed-analytics-origins]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">No embed domains have been recorded yet.</p>'; return; }
    node.innerHTML = rows.map(function (row) {
      var status = row.approved === false ? '<span class="is-warn">Review domain</span>' : '<span>Allowed / unrestricted</span>';
      return '<article><div><strong>' + esc(row.origin_host || 'Unknown origin') + '</strong>' + status + '</div><b>' + count(row.total) + '</b><small>' + count(row.submitted) + ' submissions · last seen ' + esc(row.last_seen || '—') + '</small></article>';
    }).join('');
  }

  function renderCampaignTable(rows) {
    var table = root.querySelector('[data-embed-analytics-campaign-table]');
    if (!table) return;
    rows = rows || [];
    if (!rows.length) { table.innerHTML = '<tbody><tr><td><div class="mg-empty-actions"><strong>No campaign embed activity yet.</strong><p>Run the QA page or copy an embed code from Campaigns to start collecting website analytics.</p><a href="/merchant-campaigns.php">Open Campaigns</a><a href="/merchant-campaign-embed-qa.php">Run QA page</a></div></td></tr></tbody>'; return; }
    table.innerHTML = '<thead><tr><th>Campaign</th><th>Loads</th><th>Opens</th><th>Submissions</th><th>Conversion</th><th>Last Event</th><th>Actions</th></tr></thead><tbody>' + rows.map(function (row) {
      var ref = campaignRef(row);
      return '<tr><td><strong>' + esc(row.title || 'Campaign') + '</strong><small>' + esc(typeLabel(row.campaign_type)) + ' · ' + esc(row.status || '') + '</small></td><td>' + count(row.loaded) + '</td><td>' + count(row.opened) + ' <small>' + pct(row.open_rate) + '</small></td><td>' + count(row.submitted) + '</td><td>' + pct(row.conversion_rate) + '</td><td>' + esc(row.last_event_at || '—') + '</td><td><a href="/merchant-campaigns.php">Campaigns</a><a href="/merchant-campaign-embed-qa.php?campaign=' + encodeURIComponent(ref) + '">QA</a><a href="/merchant-campaign-embed-analytics.php?campaign=' + encodeURIComponent(ref) + '">Focus</a></td></tr>';
    }).join('') + '</tbody>';
  }

  function renderRecent(rows) {
    var node = root.querySelector('[data-embed-analytics-events]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<div class="mg-empty-actions"><strong>No recent embed events yet.</strong><p>Embed events appear after a website visitor loads, opens, submits, or errors inside a campaign embed.</p><a href="/merchant-campaigns.php">Copy embed code</a><a href="/merchant-campaign-embed-qa.php">Run QA page</a></div>'; return; }
    node.innerHTML = rows.map(function (row) {
      return '<article><b>' + esc(typeLabel(row.event_type)) + '</b><span>' + esc(row.campaign_title || 'Campaign') + '</span><small>' + esc(row.origin_host || 'unknown origin') + ' · ' + esc(row.embed_mode || 'mode') + ' · ' + esc(row.created_at || '') + '</small>' + (row.page_url ? '<a href="' + esc(row.page_url) + '" target="_blank" rel="noopener">Open page URL</a>' : '') + '</article>';
    }).join('');
  }

  async function copyAnalyticsLink() {
    try {
      await navigator.clipboard.writeText(analyticsUrl());
      setAlert('<strong>Campaign analytics link copied.</strong>', 'info');
    } catch (error) {
      setAlert('<strong>Copy failed.</strong> Use your browser address bar to copy the analytics URL.', 'warn');
    }
  }

  async function loadAnalytics(pushState) {
    setAlert('<strong>Loading campaign embed analytics...</strong>', 'info');
    updateExportLinks();
    try {
      var response = await Microgifter.get(apiUrl());
      var data = response.data || response;
      renderCampaignPicker(data.campaigns || []);
      renderStats(data.totals || {});
      renderTimeline(data.timeline || []);
      renderOrigins(data.origin_rows || []);
      renderCampaignTable(data.campaign_rows || []);
      renderRecent(data.recent_events || []);
      if (data.migration_ready === false) setAlert('<strong>SQL required:</strong> ' + esc(data.sql_required || 'database/campaign_embed_settings_v2.sql'), 'warn');
      else if (!(data.recent_events || []).length && Number((data.totals || {}).loaded || 0) === 0) setAlert('<strong>No embed events yet.</strong> Use the QA page or a live website embed to start collecting data.', 'info');
      else setAlert('', '');
      if (pushState && window.history) window.history.replaceState({}, '', '/merchant-campaign-embed-analytics.php?' + queryParams().toString());
      updateExportLinks();
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to load campaign embed analytics.') + '</strong>', 'warn');
    }
  }

  var form = root.querySelector('[data-embed-analytics-filters]');
  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); loadAnalytics(true); });
  root.addEventListener('change', function (event) { if (event.target && event.target.matches('[data-embed-analytics-campaign],[data-embed-analytics-days]')) updateExportLinks(); });
  root.addEventListener('click', function (event) { if (event.target && event.target.matches('[data-copy-analytics-link]')) { event.preventDefault(); copyAnalyticsLink(); } });
  loadAnalytics(false);
});
