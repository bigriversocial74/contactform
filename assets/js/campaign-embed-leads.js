document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var root = document.querySelector('[data-campaign-embed-leads]');
  if (!root || !window.Microgifter) return;

  var selectedCampaign = root.getAttribute('data-selected-campaign') || '';
  var selectedDays = root.getAttribute('data-selected-days') || '30';
  var selectedOrigin = root.getAttribute('data-selected-origin') || '';
  var selectedSource = root.getAttribute('data-selected-source') || '';
  var lastRows = [];
  var lastPayload = {};
  var lastPlacementTests = { counts: {}, tests: [] };
  var campaignActionMap = {};

  function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); }
  function count(value) { return new Intl.NumberFormat().format(Number(value || 0)); }
  function label(value) { return String(value || '—').replace(/[_-]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }
  function data(response) { return (response && response.data) || response || {}; }

  function setAlert(message, tone) {
    var node = root.querySelector('[data-embed-leads-alert]');
    if (!node) return;
    node.hidden = !message;
    node.className = 'mg-embed-leads-alert' + (tone ? ' is-' + tone : '');
    node.innerHTML = message || '';
  }

  function setActionStatus(message, tone) {
    var node = root.querySelector('[data-embed-action-status]');
    if (!node) return;
    node.hidden = !message;
    node.className = 'mg-embed-action-status' + (tone ? ' is-' + tone : '');
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

  function apiUrl(extra) {
    var params = queryParams();
    Object.keys(extra || {}).forEach(function (key) { params.set(key, extra[key]); });
    return '/api/merchant/campaign-embed-leads.php?' + params.toString();
  }

  function testsApiUrl(extra) {
    var params = new URLSearchParams();
    var campaign = root.querySelector('[data-embed-leads-campaign]');
    if (campaign && campaign.value) params.set('campaign', campaign.value);
    Object.keys(extra || {}).forEach(function (key) { if (extra[key]) params.set(key, extra[key]); });
    return '/api/merchant/campaign-embed-placement-tests.php' + (params.toString() ? '?' + params.toString() : '');
  }

  function leadViewUrl(extra) {
    var params = queryParams();
    Object.keys(extra || {}).forEach(function (key) {
      if (extra[key] === null || extra[key] === '') params.delete(key);
      else params.set(key, extra[key]);
    });
    return new URL('/merchant-campaign-embed-leads.php?' + params.toString(), window.location.origin).toString();
  }

  function campaignRef(campaign) { return (campaign && (campaign.slug || campaign.id)) ? String(campaign.slug || campaign.id) : ''; }
  function campaignSetupUrl(campaign) { return new URL((campaign && campaign.url ? campaign.url : '/merchant-campaigns.php') + '#embed', window.location.origin).toString(); }

  function copyText(text, successMessage) {
    var value = String(text || '');
    if (!value) return Promise.resolve(false);
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(value).then(function () {
        setActionStatus('<strong>Copied.</strong> ' + esc(successMessage || 'Action link copied.'), 'success');
        return true;
      }).catch(function () { return fallbackCopy(value, successMessage); });
    }
    return fallbackCopy(value, successMessage);
  }

  function fallbackCopy(value, successMessage) {
    var input = document.createElement('textarea');
    input.value = value;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);
    input.select();
    var copied = false;
    try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
    document.body.removeChild(input);
    setActionStatus(copied ? '<strong>Copied.</strong> ' + esc(successMessage || 'Action link copied.') : '<strong>Copy failed.</strong> Select and copy manually: ' + esc(value), copied ? 'success' : 'warn');
    return Promise.resolve(copied);
  }

  function qualityBadge(quality) {
    quality = quality || {};
    var score = Number(quality.score || 0);
    var state = score >= 85 ? 'high' : (score >= 65 ? 'ready' : (score >= 45 ? 'medium' : 'low'));
    return '<span class="mg-lead-quality-badge is-' + state + '"><b>' + esc(quality.label || 'Needs context') + '</b><em>' + esc(score) + '/100</em></span>';
  }

  function priorityPill(priority) {
    var value = String(priority || 'Monitor');
    var state = value.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    return '<span class="mg-placement-priority is-' + esc(state) + '">' + esc(value) + '</span>';
  }

  function statusPill(status) {
    var value = String(status || 'planned').toLowerCase();
    return '<span class="mg-test-status is-' + esc(value) + '">' + esc(label(value)) + '</span>';
  }

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
      ['Ready Follow-Up', count(totals.ready_for_follow_up)],
      ['Avg Quality', count(totals.average_quality_score) + '/100']
    ];
    node.innerHTML = cards.map(function (card) { return '<article><b>' + esc(card[1]) + '</b><span>' + esc(card[0]) + '</span></article>'; }).join('');
  }

  function summaryForCampaign(data, campaign) {
    var ref = campaignRef(campaign);
    var summaries = (data && data.campaign_summaries) || [];
    return summaries.find(function (summary) {
      var item = summary.campaign || {};
      return ref && (item.slug === ref || item.id === ref);
    }) || {};
  }

  function topMode(data) {
    var modes = (((data || {}).totals || {}).top_modes) || [];
    return modes[0] ? String(modes[0].value || '') : '';
  }

  function buildStartPayload(ref) {
    var stored = campaignActionMap[ref] || {};
    var action = stored.action || {};
    var campaign = action.campaign || {};
    var summary = stored.summary || {};
    var topDomain = summary.top_domain || {};
    var topPage = summary.top_page || {};
    var topSource = summary.top_source || {};
    return {
      action: 'start',
      campaign_ref: campaignRef(campaign) || ref,
      origin_host: topDomain.value || topDomain.origin_host || '',
      page_url: topPage.value || topPage.page_url || '',
      page_path: topPage.page_path || '',
      source: topSource.value || action.source_signal || '',
      embed_mode: stored.embed_mode || '',
      placement_label: action.current_winner || '',
      next_test: action.next_test || '',
      ready_rate: action.ready_rate || summary.ready_rate || 0,
      average_quality_score: action.average_quality_score || summary.average_quality_score || 0,
      recommended_action: action.recommended_action || '',
      notes: 'Started from Campaign Embed v4.7 Merchant Action Center'
    };
  }

  async function postPlacementTest(payload) {
    if (!window.Microgifter || !window.Microgifter.post) throw new Error('Microgifter POST helper is not loaded.');
    var response = await window.Microgifter.post('/api/merchant/campaign-embed-placement-tests.php', payload || {});
    var result = data(response);
    renderPlacementTests(result);
    return result;
  }

  async function startPersistentTest(ref) {
    try {
      var result = await postPlacementTest(buildStartPayload(ref));
      setActionStatus('<strong>Placement test started.</strong> Status is now persisted in the database.', 'success');
      await loadPlacementTests();
      return result;
    } catch (error) {
      setActionStatus('<strong>Unable to start persistent test.</strong> ' + esc(error.message || 'Import database/campaign_embed_placement_tests_v4_7.sql and try again.'), 'warn');
    }
  }

  async function updatePersistentTest(testId, action) {
    try {
      var result = await postPlacementTest({ action: action, test_id: testId });
      setActionStatus('<strong>Placement test updated.</strong> ' + esc(label(action)) + ' saved.', 'success');
      if (action === 'compare' && result.compare_url) window.location.href = result.compare_url;
      else await loadPlacementTests();
    } catch (error) {
      setActionStatus('<strong>Unable to update placement test.</strong> ' + esc(error.message || 'Try again after confirming the v4.7 SQL migration is installed.'), 'warn');
    }
  }

  function renderPlacementTests(payload) {
    payload = payload || {};
    lastPlacementTests = payload;
    var summaryNode = root.querySelector('[data-embed-test-summary]');
    var historyNode = root.querySelector('[data-embed-test-history]');
    var counts = payload.counts || {};
    var tests = payload.tests || [];
    if (summaryNode) {
      summaryNode.innerHTML = ['running', 'planned', 'paused', 'completed'].map(function (key) {
        return '<article><b>' + count(counts[key] || 0) + '</b><span>' + esc(label(key)) + '</span></article>';
      }).join('');
    }
    if (!historyNode) return;
    if (!tests.length) {
      historyNode.innerHTML = '<p class="mg-empty-copy">No persistent placement tests yet. Use <strong>Start persistent test</strong> from a campaign action card after attribution data appears.</p>';
      return;
    }
    historyNode.innerHTML = tests.map(function (test) {
      var campaign = test.campaign || {};
      var compareUrl = leadViewUrl({ campaign: campaign.slug || campaign.id || null, origin_host: test.origin_host || null, source: test.source || null, days: '30' });
      var actions = '<div class="mg-action-button-row">';
      if (test.status === 'running') actions += '<button type="button" data-embed-test-action="pause" data-embed-test-id="' + esc(test.id) + '">Pause</button><button type="button" data-embed-test-action="complete" data-embed-test-id="' + esc(test.id) + '">End test</button>';
      if (test.status === 'paused') actions += '<button type="button" data-embed-test-action="resume" data-embed-test-id="' + esc(test.id) + '">Resume</button><button type="button" data-embed-test-action="complete" data-embed-test-id="' + esc(test.id) + '">End test</button>';
      if (test.status === 'planned') actions += '<button type="button" data-embed-test-action="resume" data-embed-test-id="' + esc(test.id) + '">Start</button>';
      actions += '<button type="button" data-embed-test-action="compare" data-embed-test-id="' + esc(test.id) + '">Compare results</button><a href="' + esc(compareUrl) + '">View filtered leads</a></div>';
      return '<article class="mg-embed-test-card"><div>' + statusPill(test.status) + '<strong>' + esc(campaign.title || 'Campaign') + '</strong></div><p>' + esc(test.placement_label || test.origin_host || 'Website embed placement') + '</p><dl><dt>Domain</dt><dd>' + esc(test.origin_host || '—') + '</dd><dt>Page</dt><dd>' + esc(test.page_path || test.page_url || '—') + '</dd><dt>Source</dt><dd>' + esc(label(test.source || 'website_embed')) + '</dd><dt>Mode</dt><dd>' + esc(test.embed_mode || '—') + '</dd><dt>Started</dt><dd>' + esc(test.started_at || '—') + '</dd><dt>Ended</dt><dd>' + esc(test.ended_at || '—') + '</dd></dl>' + actions + '</article>';
    }).join('');
  }

  async function loadPlacementTests() {
    try {
      var response = await window.Microgifter.get(testsApiUrl());
      renderPlacementTests(data(response));
    } catch (error) {
      renderPlacementTests({ counts: {}, tests: [] });
      var historyNode = root.querySelector('[data-embed-test-history]');
      if (historyNode) historyNode.innerHTML = '<p class="mg-empty-copy"><strong>Placement test tracking needs SQL.</strong> Import <code>database/campaign_embed_placement_tests_v4_7.sql</code>, then refresh this page.</p>';
    }
  }

  function renderActionCenter(dataPayload) {
    dataPayload = dataPayload || {};
    var placement = dataPayload.placement_intelligence || {};
    var campaignActions = placement.campaign_actions || [];
    var primaryNode = root.querySelector('[data-embed-action-primary]');
    var campaignNode = root.querySelector('[data-embed-action-campaigns]');
    var followNode = root.querySelector('[data-embed-action-followups]');
    var filteredUrl = leadViewUrl();
    campaignActionMap = {};
    if (primaryNode) {
      primaryNode.innerHTML = [
        '<button type="button" data-embed-action-copy-url="' + esc(filteredUrl) + '" data-embed-action-copy-message="Filtered Embed Leads view copied.">Copy filtered lead view</button>',
        '<button type="button" data-embed-action-export>Export placement report</button>',
        '<a href="/merchant-campaigns.php">Open campaigns</a>',
        '<a href="/merchant-campaign-embed-qa.php">Run Embed QA</a>',
        '<a href="/merchant-notifications.php">Open notifications</a>'
      ].join('');
    }
    if (campaignNode) {
      campaignNode.innerHTML = campaignActions.length ? campaignActions.map(function (action) {
        var campaign = action.campaign || {};
        var ref = campaignRef(campaign);
        var summary = summaryForCampaign(dataPayload, campaign);
        campaignActionMap[ref || 'all'] = { action: action, summary: summary, embed_mode: topMode(dataPayload) };
        var leadsUrl = leadViewUrl({ campaign: ref || null });
        var setupUrl = campaignSetupUrl(campaign);
        return '<article class="mg-action-campaign-card"><div>' + priorityPill(action.priority) + '<strong>' + esc(campaign.title || 'Campaign') + '</strong></div><p>' + esc(action.recommended_action || 'Review this campaign placement.') + '</p><div class="mg-action-button-row">' + (campaign.url ? '<a href="' + esc(campaign.url) + '">Open campaign</a>' : '<a href="/merchant-campaigns.php">Open campaigns</a>') + '<a href="' + esc(leadsUrl) + '">View filtered leads</a><button type="button" data-embed-action-copy-url="' + esc(setupUrl) + '" data-embed-action-copy-message="Campaign embed setup link copied.">Copy embed setup link</button><button type="button" data-embed-action-start-test="' + esc(ref || 'all') + '">Start persistent test</button></div><small>Persistent tracking stores campaign, domain, page, source, mode, status, start/end dates, and comparison metadata.</small></article>';
      }).join('') : '<p class="mg-empty-copy">Campaign action buttons appear after placement intelligence has campaign-level data.</p>';
    }
    if (followNode) {
      var rec = placement.recommended_next_action || 'Review placement intelligence and start a campaign follow-up workflow.';
      followNode.innerHTML = '<article class="mg-action-followup-card"><strong>Recommended workflow</strong><p>' + esc(rec) + '</p><div class="mg-action-button-row"><a href="/merchant-campaigns.php?intent=follow_up&source=campaign_embed">Create follow-up campaign</a><a href="/merchant-crm.php">Open Merchant CRM</a><button type="button" data-embed-action-copy-url="' + esc(filteredUrl) + '" data-embed-action-copy-message="Lead follow-up view copied.">Copy follow-up view</button></div></article><article class="mg-action-followup-card"><strong>Report handoff</strong><p>Export current filters as CSV, then share the placement report with your team.</p><div class="mg-action-button-row"><button type="button" data-embed-action-export>Export CSV report</button><a href="/merchant-campaign-embed-analytics.php">Open analytics</a></div></article>';
    }
  }

  function renderPlacementIntelligence(placement) {
    placement = placement || {};
    var nextNode = root.querySelector('[data-embed-placement-next]');
    var cardsNode = root.querySelector('[data-embed-placement-cards]');
    var actionsNode = root.querySelector('[data-embed-placement-actions]');
    var experimentsNode = root.querySelector('[data-embed-placement-experiments]');
    if (nextNode) nextNode.innerHTML = placement.recommended_next_action ? '<strong>Recommended Next Action</strong><p>' + esc(placement.recommended_next_action) + '</p>' : '<p class="mg-empty-copy">Placement recommendations appear after attributed embed leads are captured.</p>';
    if (cardsNode) {
      var cards = placement.summary_cards || [];
      cardsNode.innerHTML = cards.length ? cards.map(function (card) { return '<article><strong>' + esc(card.value || '—') + '</strong><span>' + esc(card.label || '') + '</span><p>' + esc(card.detail || '') + '</p></article>'; }).join('') : '<p class="mg-empty-copy">No placement cards yet.</p>';
    }
    if (actionsNode) {
      var actions = placement.campaign_actions || [];
      actionsNode.innerHTML = actions.length ? actions.map(function (action) {
        var campaign = action.campaign || {};
        return '<article class="mg-placement-action-card"><div>' + priorityPill(action.priority) + '<strong>' + esc(campaign.title || 'Campaign') + '</strong></div><p>' + esc(action.recommended_action || '') + '</p><small>' + esc(action.reason || '') + '</small><dl><dt>Current winner</dt><dd>' + esc(action.current_winner || '—') + '</dd><dt>Next test</dt><dd>' + esc(action.next_test || '—') + '</dd><dt>Ready / Quality</dt><dd>' + esc(action.ready_rate || 0) + '% · ' + esc(action.average_quality_score || 0) + '/100</dd></dl>' + (campaign.url ? '<a href="' + esc(campaign.url) + '">Open campaign</a>' : '') + '</article>';
      }).join('') : '<p class="mg-empty-copy">Campaign placement actions appear after a campaign has attributed leads.</p>';
    }
    if (experimentsNode) {
      var experiments = placement.experiments || [];
      experimentsNode.innerHTML = experiments.length ? experiments.map(function (item) { return '<article class="mg-placement-experiment"><div>' + priorityPill(item.priority) + '<strong>' + esc(item.title || 'Experiment') + '</strong></div><p>' + esc(item.detail || '') + '</p></article>'; }).join('') : '<p class="mg-empty-copy">No placement experiments queued yet.</p>';
    }
  }

  function renderPerformance(performance) {
    performance = performance || {};
    var insightNode = root.querySelector('[data-embed-performance-insights]');
    var qualityNode = root.querySelector('[data-embed-quality-breakdown]');
    var recNode = root.querySelector('[data-embed-recommendations]');
    if (insightNode) {
      var cards = performance.insight_cards || [];
      insightNode.innerHTML = cards.length ? cards.map(function (card) { return '<article><strong>' + esc(card.value || '—') + '</strong><span>' + esc(card.label || '') + '</span><p>' + esc(card.detail || '') + '</p></article>'; }).join('') : '<p class="mg-empty-copy">Performance insights appear after attributed embed leads are captured.</p>';
    }
    if (qualityNode) {
      var quality = performance.quality_breakdown || [];
      qualityNode.innerHTML = quality.length ? quality.map(function (item) { return '<article><b>' + count(item.total) + '</b><span>' + esc(item.label || 'Quality') + '</span></article>'; }).join('') : '<p class="mg-empty-copy">No lead quality mix yet.</p>';
    }
    if (recNode) {
      var recs = performance.recommendations || [];
      recNode.innerHTML = recs.length ? '<ul>' + recs.map(function (item) { return '<li>' + esc(item) + '</li>'; }).join('') + '</ul>' : '<p class="mg-empty-copy">Recommendations appear after campaign embeds generate attribution data.</p>';
    }
  }

  function renderNotificationBadge(rows) {
    var node = root.querySelector('[data-embed-leads-notification-badge]');
    if (!node) return;
    var since = Date.now() - (24 * 60 * 60 * 1000);
    var recent = (rows || []).filter(function (row) { var time = Date.parse(row.created_at || ''); return !Number.isNaN(time) && time >= since; });
    if (!recent.length) { node.hidden = true; node.innerHTML = ''; return; }
    var latest = recent[0] || {};
    node.hidden = false;
    node.innerHTML = '<strong>New attributed leads</strong><span>' + count(recent.length) + ' website embed lead' + (recent.length === 1 ? '' : 's') + ' in the last 24 hours.</span><a href="/merchant-notifications.php">Open Notifications</a>' + (latest.origin_host ? '<em>Latest source: ' + esc(latest.origin_host) + '</em>' : '');
  }

  function renderDomains(rows) {
    var node = root.querySelector('[data-embed-leads-domains]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">No lead domains have been attributed yet.</p>'; return; }
    node.innerHTML = rows.map(function (row) { return '<button type="button" data-filter-domain="' + esc(row.origin_host || '') + '"><strong>' + esc(row.origin_host || 'Unknown domain') + '</strong><b>' + count(row.total) + '</b><span>embed leads</span></button>'; }).join('');
  }

  function renderPages(rows) {
    var node = root.querySelector('[data-embed-leads-pages]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">No top lead pages yet.</p>'; return; }
    node.innerHTML = rows.map(function (row) {
      var path = row.page_path || row.page_url || 'Unknown page';
      var page = row.page_url ? '<a href="' + esc(row.page_url) + '" target="_blank" rel="noopener">Open page</a>' : '<span>No page URL</span>';
      return '<article class="mg-embed-leads-page-card"><strong>' + esc(path) + '</strong><b>' + count(row.total) + '</b><small>embed leads</small>' + page + '</article>';
    }).join('');
  }

  function renderCampaignSummaries(rows) {
    var node = root.querySelector('[data-embed-leads-campaign-summaries]');
    if (!node) return;
    rows = rows || [];
    if (!rows.length) { node.innerHTML = '<p class="mg-empty-copy">Campaign summaries appear after website embed leads are attributed.</p>'; return; }
    node.innerHTML = rows.map(function (summary) {
      var campaign = summary.campaign || {};
      var domain = summary.top_domain ? summary.top_domain.value + ' · ' + count(summary.top_domain.total) : 'No domain yet';
      var source = summary.top_source ? summary.top_source.value + ' · ' + count(summary.top_source.total) : 'No source yet';
      var placement = summary.placement_action || {};
      return '<article class="mg-embed-leads-campaign-card"><div><strong>' + esc(campaign.title || 'Campaign') + '</strong><small>' + esc(label(campaign.campaign_type || '')) + '</small></div><b>' + count(summary.total_embed_leads) + '</b><span>Total embed leads</span><p><em>Ready:</em> ' + esc(summary.ready_for_follow_up || 0) + ' · ' + esc(summary.ready_rate || 0) + '%</p><p><em>Avg quality:</em> ' + esc(summary.average_quality_score || 0) + '/100</p><p><em>Top domain:</em> ' + esc(domain) + '</p><p><em>Top source:</em> ' + esc(source) + '</p>' + (placement.recommended_action ? '<p><em>Next action:</em> ' + esc(placement.recommended_action) + '</p>' : '') + (campaign.url ? '<a href="' + esc(campaign.url) + '">Open campaign</a>' : '') + '</article>';
    }).join('');
  }

  function renderFilterSummary(dataPayload, rowCount) {
    var node = root.querySelector('[data-embed-leads-filter-summary]');
    if (!node) return;
    var params = queryParams();
    var filters = [];
    filters.push('Window: last ' + esc(params.get('days') || '30') + ' days');
    if (params.get('campaign')) filters.push('Campaign: ' + esc(params.get('campaign')));
    if (params.get('origin_host')) filters.push('Domain: ' + esc(params.get('origin_host')));
    if (params.get('source')) filters.push('Source: ' + esc(label(params.get('source'))));
    var total = dataPayload && dataPayload.totals ? dataPayload.totals.total_embed_leads : rowCount;
    var ready = dataPayload && dataPayload.totals ? dataPayload.totals.ready_for_follow_up : 0;
    node.innerHTML = '<strong>' + count(total) + '</strong> attributed embed lead' + (Number(total || 0) === 1 ? '' : 's') + ' · <strong>' + count(ready) + '</strong> ready for follow-up · ' + filters.join(' · ');
  }

  function rowActions(row) {
    var contact = row.crm_contact || {};
    var campaign = row.campaign || {};
    var campaignContact = row.campaign_contact || {};
    var actions = '<button type="button" data-lead-detail="' + esc(row.lead_event_id || '') + '">Details</button>';
    if (contact.url) actions += '<a href="' + esc(contact.url) + '">CRM Profile</a>';
    if (campaignContact.url) actions += '<a href="' + esc(campaignContact.url) + '">Campaign Contact</a>';
    if (campaign.url) actions += '<a href="' + esc(campaign.url) + '">Campaign</a>';
    return actions;
  }

  function renderRows(rows) {
    var table = root.querySelector('[data-embed-leads-table]');
    if (!table) return;
    rows = rows || [];
    lastRows = rows;
    if (!rows.length) {
      table.innerHTML = '<tbody><tr><td><div class="mg-empty-actions"><strong>No website embed leads yet.</strong><p>Embed leads appear after a public campaign form submits with website attribution.</p><a href="/merchant-campaigns.php">Open Campaigns</a><a href="/merchant-campaign-embed-qa.php">Run Embed QA</a><a href="/merchant-campaign-embed-analytics.php">View Embed Analytics</a></div></td></tr></tbody>';
      return;
    }
    table.innerHTML = '<thead><tr><th>Lead</th><th>Quality</th><th>Campaign</th><th>Source</th><th>Domain / Page</th><th>Mode</th><th>Created</th><th>Actions</th></tr></thead><tbody>' + rows.map(function (row) {
      var contact = row.crm_contact || {};
      var campaign = row.campaign || {};
      var contactName = contact.name || contact.email || 'Lead';
      var page = row.page_url ? '<a href="' + esc(row.page_url) + '" target="_blank" rel="noopener">Open page</a>' : '<small>No page URL</small>';
      return '<tr><td><strong>' + esc(contactName) + '</strong><small>' + esc(contact.email || '') + '</small></td><td>' + qualityBadge(row.lead_quality) + '</td><td><strong>' + esc(campaign.title || 'Campaign') + '</strong><small>' + esc(label(campaign.campaign_type || '')) + '</small></td><td>' + esc(label(row.source || row.embed_source || 'website_embed')) + '</td><td><strong>' + esc(row.origin_host || 'Unknown') + '</strong>' + page + '</td><td>' + esc(row.embed_mode || '—') + '</td><td>' + esc(row.created_at || '—') + '</td><td>' + rowActions(row) + '</td></tr>';
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

  function findRow(id) { return (lastRows || []).find(function (row) { return String(row.lead_event_id || '') === String(id || ''); }) || null; }
  function closeDrawer() { var drawer = root.querySelector('[data-embed-leads-drawer]'); if (drawer) drawer.hidden = true; }

  function openDrawer(row) {
    var drawer = root.querySelector('[data-embed-leads-drawer]');
    var content = root.querySelector('[data-embed-leads-drawer-content]');
    if (!drawer || !content || !row) return;
    var contact = row.crm_contact || {};
    var campaign = row.campaign || {};
    var quality = row.lead_quality || {};
    var timeline = (row.timeline || []).map(function (item) { return '<li><span>' + esc(item.label || '') + '</span><strong>' + esc(item.value || '—') + '</strong></li>'; }).join('');
    var signals = (quality.signals || []).map(function (item) { return '<li>' + esc(item) + '</li>'; }).join('');
    var missing = (quality.missing || []).map(function (item) { return '<li>' + esc(item) + '</li>'; }).join('');
    var links = '';
    if (contact.url) links += '<a class="mg-btn mg-btn-primary" href="' + esc(contact.url) + '">Open CRM Profile</a>';
    if ((row.campaign_contact || {}).url) links += '<a class="mg-btn mg-btn-soft" href="' + esc(row.campaign_contact.url) + '">Campaign Contact</a>';
    if (campaign.url) links += '<a class="mg-btn mg-btn-ghost" href="' + esc(campaign.url) + '">Campaign</a>';
    content.innerHTML = '<span class="mg-eyebrow">Lead detail</span><h2>' + esc(contact.name || contact.email || 'Website embed lead') + '</h2><p>' + esc(row.value_summary || 'Attributed website embed lead') + '</p><div class="mg-embed-leads-detail-quality">' + qualityBadge(quality) + '<span>' + (quality.ready_for_follow_up ? 'Ready for merchant follow-up' : 'Needs more follow-up context') + '</span></div><div class="mg-embed-leads-detail-grid"><article><b>Campaign</b><span>' + esc(campaign.title || 'Campaign') + '</span></article><article><b>Source</b><span>' + esc(label(row.source || row.embed_source || 'website_embed')) + '</span></article><article><b>Origin Host</b><span>' + esc(row.origin_host || 'Unknown') + '</span></article><article><b>Embed Mode</b><span>' + esc(row.embed_mode || '—') + '</span></article></div><h3>Quality signals</h3><div class="mg-embed-quality-lists"><ul>' + (signals || '<li>No positive signals yet.</li>') + '</ul><ul>' + (missing || '<li>No major gaps.</li>') + '</ul></div><h3>Timeline</h3><ul class="mg-embed-leads-timeline">' + timeline + '</ul><h3>Follow-up links</h3><div class="mg-embed-leads-drawer-actions">' + (links || '<small>No linked CRM records found.</small>') + '</div>';
    drawer.hidden = false;
  }

  function exportCsv() { window.location.href = apiUrl({ format: 'csv' }); }

  async function loadLeads(pushState) {
    setAlert('<strong>Loading website embed leads...</strong>', 'info');
    try {
      var response = await Microgifter.get(apiUrl());
      var payload = data(response);
      lastPayload = payload;
      renderCampaignPicker(payload.campaigns || []);
      renderStats(payload.totals || {});
      renderActionCenter(payload);
      renderPlacementIntelligence(payload.placement_intelligence || {});
      renderPerformance(payload.performance || {});
      renderCampaignSummaries(payload.campaign_summaries || []);
      renderPages((payload.totals || {}).top_pages || []);
      renderDomains((payload.totals || {}).top_domains || []);
      renderRows(payload.rows || []);
      renderNotificationBadge(payload.rows || []);
      renderFilterSummary(payload, (payload.rows || []).length);
      await loadPlacementTests();
      if (payload.schema_ready === false) setAlert('<strong>Embed leads data is not ready.</strong> Import the existing CRM/campaign tables before using this view.', 'warn');
      else if (!(payload.rows || []).length) setAlert('<strong>No embed leads found for these filters.</strong> Run Embed QA or submit a public website embed to create an attributed row.', 'info');
      else setAlert('', '');
      if (pushState && window.history) window.history.replaceState({}, '', '/merchant-campaign-embed-leads.php?' + queryParams().toString());
    } catch (error) {
      setAlert('<strong>' + esc(error.message || 'Unable to load website embed leads.') + '</strong>', 'warn');
    }
  }

  var form = root.querySelector('[data-embed-leads-filters]');
  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); closeDrawer(); loadLeads(true); });
  root.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-filter-domain]') : null;
    var reset = event.target && event.target.closest ? event.target.closest('[data-embed-leads-reset]') : null;
    var detail = event.target && event.target.closest ? event.target.closest('[data-lead-detail]') : null;
    var close = event.target && event.target.closest ? event.target.closest('[data-embed-leads-close]') : null;
    var exportButton = event.target && event.target.closest ? event.target.closest('[data-embed-leads-export], [data-embed-action-export]') : null;
    var copyButton = event.target && event.target.closest ? event.target.closest('[data-embed-action-copy-url]') : null;
    var startButton = event.target && event.target.closest ? event.target.closest('[data-embed-action-start-test]') : null;
    var testButton = event.target && event.target.closest ? event.target.closest('[data-embed-test-action]') : null;
    if (close) { closeDrawer(); return; }
    if (exportButton) { event.preventDefault(); exportCsv(); return; }
    if (copyButton) { event.preventDefault(); copyText(copyButton.getAttribute('data-embed-action-copy-url') || '', copyButton.getAttribute('data-embed-action-copy-message') || 'Copied.'); return; }
    if (startButton) { event.preventDefault(); startPersistentTest(startButton.getAttribute('data-embed-action-start-test') || 'all'); return; }
    if (testButton) { event.preventDefault(); updatePersistentTest(testButton.getAttribute('data-embed-test-id') || '', testButton.getAttribute('data-embed-test-action') || 'compare'); return; }
    if (detail) { openDrawer(findRow(detail.getAttribute('data-lead-detail'))); return; }
    if (reset) { resetFilters(); closeDrawer(); loadLeads(true); return; }
    if (!button) return;
    var input = root.querySelector('[data-embed-leads-origin]');
    if (input) input.value = button.getAttribute('data-filter-domain') || '';
    closeDrawer();
    loadLeads(true);
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDrawer(); });
  loadLeads(false);
});
