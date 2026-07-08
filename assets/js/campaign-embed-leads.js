document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-campaign-embed-leads]');
  if (!root || !window.Microgifter) return;

  var selectedCampaign = root.getAttribute('data-selected-campaign') || '';
  var selectedDays = root.getAttribute('data-selected-days') || '30';
  var selectedOrigin = root.getAttribute('data-selected-origin') || '';
  var selectedSource = root.getAttribute('data-selected-source') || '';

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function label(value) { return String(value || '—').replace(/[_-]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }

  function setAlert(message, tone) {
    var node = root.querySelector('[data-embed-leads-alert]');
    if (!node) return;
    node.hidden = !message;
    node.className = 'mg-embed-leads-alert' + (tone ? ' is-' + tone : '');
    node.innerHTML = message || '';
  }

  function queryParams() {
    var params = new URLSearchParams();
    var campaign = root.querySelector('[data-embed-leads-campaign]');
    var days = root.querySelector('[data-embed-leads-days]');
    var origin = root.querySelector('[data-embed-leads-origin]');
    var source = root.querySelector('[data-embed-leads-source]');
    params.set('days', days ? days.value : selectedDays);
    if (campaign && campaign.value) params.set('campaign', campaign.value);
    if (origin && origin.value.trim()) params.set('origin_host', origin.value.trim());
    if (source && source.value.trim()) params.set('source', source.value.trim());
    return params;
  }

  function apiUrl() { return '/api/merchant/campaign-embed-leads.php?' + queryParams().toString(); }

  function renderCampaignPicker(campaigns) {
    var select = root.querySelector('[data-embed-leads-campaign]');
    if (!select) return;
    var current = select.value || selectedCampaign;
    select.innerHTML = '<option value="">All campaigns</option>' + (campaigns || []).map(function (campaign) {
      var ref = campaign.slug || campaign.id;
      return '<option value="' + esc(ref) + '"' + (ref === current ? ' selected' : '') + '>' + esc(campaign.title || 'Campaign') + ' · ' + esc(campaign.status || '') + '</option>';
    }).join('');
  }

  function renderStats(totals) {
    var node = root.querySelector('[data-embed-leads-stats]');
    if (!node) return;
    totals = totals || {};
    var cards = [
      ['Total Embed Leads', count(totals.total_embed_leads)],
      ['New Contacts', count(totals.new_contacts)],
      ['Returning Contacts', count(totals.returning_contacts)],
      ['Campaigns', count(totals.campaigns)]
    ];
    node.innerHTML = cards.map(function (card) { return '<article><b>' + esc(card[1]) + '</b><span>' + esc(card[0]) + '</span></article>'; }).join('');
  }

  function renderDomains(rows) {
    var node = root.querySelector('[data-embed-leads-domains]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">No lead domains have been attributed yet.</p>'; return; }
    node.innerHTML = rows.map(function (row) {
      return '<button type="button" data-filter-domain="' + esc(row.origin_host || '') + '"><strong>' + esc(row.origin_host || 'Unknown domain') + '</strong><b>' + count(row.total) + '</b><span>embed leads</span></button>';
    }).join('');
  }

  function renderFilterSummary(data, rowCount) {
    var node = root.querySelector('[data-embed-leads-filter-summary]');
    if (!node) return;
    var params = queryParams();
    var filters = [];
    filters.push('Window: last ' + esc(params.get('days') || '30') + ' days');
    if (params.get('campaign')) filters.push('Campaign: ' + esc(params.get('campaign')));
    if (params.get('origin_host')) filters.push('Domain: ' + esc(params.get('origin_host')));
    if (params.get('source')) filters.push('Source: ' + esc(label(params.get('source'))));
    var total = data && data.totals ? data.totals.total_embed_leads : rowCount;
    node.innerHTML = '<strong>' + count(total) + '</strong> attributed embed lead' + (Number(total || 0) === 1 ? '' : 's') + ' · ' + filters.join(' · ');
  }

  function renderRows(rows) {
    var table = root.querySelector('[data-embed-leads-table]');
    if (!table) return;
    rows = rows || [];
    if (!rows.length) {
      table.innerHTML = '<tbody><tr><td><div class="mg-empty-actions"><strong>No website embed leads yet.</strong><p>Embed leads appear after a public campaign form submits with website attribution.</p><a href="/merchant-campaigns.php">Open Campaigns</a><a href="/merchant-campaign-embed-qa.php">Run Embed QA</a><a href="/merchant-campaign-embed-analytics.php">View Embed Analytics</a></div></td></tr></tbody>';
      return;
    }
    table.innerHTML = '<thead><tr><th>Lead</th><th>Campaign</th><th>Source</th><th>Domain / Page</th><th>Mode</th><th>Created</th><th>Actions</th></tr></thead><tbody>' + rows.map(function (row) {
      var contact = row.crm_contact || {};
      var campaign = row.campaign || {};
      var campaignContact = row.campaign_contact || {};
      var contactName = contact.name || contact.email || 'Lead';
      var page = row.page_url ? '<a href="' + esc(row.page_url) + '" target="_blank" rel="noopener">Open page</a>' : '<small>No page URL</small>';
      var actions = '';
      if (contact.url) actions += '<a href="' + esc(contact.url) + '">CRM Profile</a>';
      if (campaignContact.url) actions += '<a href="' + esc(campaignContact.url) + '">Campaign Contact</a>';
      if (campaign.url) actions += '<a href="' + esc(campaign.url) + '">Campaign</a>';
      if (!actions) actions = '<small>No linked records</small>';
      return '<tr><td><strong>' + esc(contactName) + '</strong><small>' + esc(contact.email || '') + '</small></td><td><strong>' + esc(campaign.title || 'Campaign') + '</strong><small>' + esc(label(campaign.campaign_type || '')) + '</small></td><td>' + esc(label(row.source || row.embed_source || 'website_embed')) + '</td><td><strong>' + esc(row.origin_host || 'Unknown') + '</strong>' + page + '</td><td>' + esc(row.embed_mode || '—') + '</td><td>' + esc(row.created_at || '—') + '</td><td>' + actions + '</td></tr>';
    }).join('') + '</tbody>';
  }

  function resetFilters() {
    var campaign = root.querySelector('[data-embed-leads-campaign]');
    var days = root.querySelector('[data-embed-leads-days]');
    var origin = root.querySelector('[data-embed-leads-origin]');
    var source = root.querySelector('[data-embed-leads-source]');
    if (campaign) campaign.value = '';
    if (days) days.value = '30';
    if (origin) origin.value = '';
    if (source) source.value = '';
    selectedCampaign = '';
    selectedDays = '30';
    selectedOrigin = '';
    selectedSource = '';
  }

  async function loadLeads(pushState) {
    setAlert('<strong>Loading website embed leads...</strong>', 'info');
    try {
      var response = await Microgifter.get(apiUrl());
      var data = response.data || response;
      renderCampaignPicker(data.campaigns || []);
      renderStats(data.totals || {});
      renderDomains((data.totals || {}).top_domains || []);
      renderRows(data.rows || []);
      renderFilterSummary(data, (data.rows || []).length);
      if (data.schema_ready === false) setAlert('<strong>Embed leads data is not ready.</strong> No new SQL is required by v4.1; this view uses existing CRM/campaign tables when present.', 'warn');
      else if (!(data.rows || []).length) setAlert('<strong>No embed leads found for these filters.</strong> Run Embed QA or submit a public website embed to create an attributed row.', 'info');
      else setAlert('', '');
      if (pushState && window.history) window.history.replaceState({}, '', '/merchant-campaign-embed-leads.php?' + queryParams().toString());
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to load website embed leads.') + '</strong>', 'warn');
    }
  }

  var form = root.querySelector('[data-embed-leads-filters]');
  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); loadLeads(true); });
  root.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-filter-domain]') : null;
    var reset = event.target && event.target.closest ? event.target.closest('[data-embed-leads-reset]') : null;
    if (reset) { resetFilters(); loadLeads(true); return; }
    if (!button) return;
    var input = root.querySelector('[data-embed-leads-origin]');
    if (input) input.value = button.getAttribute('data-filter-domain') || '';
    loadLeads(true);
  });
  loadLeads(false);
});
