document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var app = document.querySelector('[data-creator-campaign-analytics]');
  if (!app || !window.Microgifter) return;

  var endpoint = String(app.dataset.endpoint || '');
  var mode = app.dataset.mode === 'creator' ? 'creator' : 'merchant';
  var form = app.querySelector('[data-cca-filters]');
  var state = { data: null, loading: false, activeTab: 'overview' };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function percent(bps) { return (Number(bps || 0) / 100).toFixed(2) + '%'; }
  function money(amountMinor, currency) {
    try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: String(currency || 'USD') }).format(Number(amountMinor || 0) / 100); }
    catch (error) { return String(currency || 'USD') + ' ' + (Number(amountMinor || 0) / 100).toFixed(2); }
  }
  function moneyMap(map, field) {
    var keys = Object.keys(map || {}).sort();
    if (!keys.length) return '<span class="mg-cca-muted">No financial activity</span>';
    return keys.map(function (currency) {
      var value = map[currency];
      if (value && typeof value === 'object') value = value[field] || 0;
      return '<span class="mg-cca-money-chip"><strong>' + esc(money(value, currency)) + '</strong><small>' + esc(currency) + '</small></span>';
    }).join('');
  }
  function rangeLabel(range) {
    if (!range) return 'Selected range';
    if (range.key === 'all_time') return 'All time';
    return String(range.from || '') + ' – ' + String(range.to || '');
  }
  function queryString(extra) {
    var params = new URLSearchParams(new FormData(form));
    Array.from(params.entries()).forEach(function (pair) { if (!String(pair[1] || '').trim()) params.delete(pair[0]); });
    Object.keys(extra || {}).forEach(function (key) { params.set(key, extra[key]); });
    return params.toString();
  }
  function setLoading(on) {
    state.loading = on;
    app.querySelector('[data-cca-loading]').classList.toggle('mg-hidden', !on);
    app.querySelector('[data-cca-error]').classList.add('mg-hidden');
    if (on) app.querySelector('[data-cca-content]').classList.add('mg-hidden');
  }
  function showError(message) {
    setLoading(false);
    app.querySelector('[data-cca-error-message]').textContent = message || 'Unable to load analytics.';
    app.querySelector('[data-cca-error]').classList.remove('mg-hidden');
  }
  function live(message) { app.querySelector('[data-cca-live]').textContent = message || ''; }

  function optionHtml(value, label, selected) {
    return '<option value="' + esc(value) + '"' + (String(value) === String(selected) ? ' selected' : '') + '>' + esc(label) + '</option>';
  }
  function populateOptions(data) {
    var campaign = app.querySelector('[data-cca-campaign]');
    var participant = app.querySelector('[data-cca-participant]');
    var selectedCampaign = campaign.value;
    var selectedParticipant = participant.value;
    campaign.innerHTML = optionHtml('', 'All campaigns', selectedCampaign) + (data.options.campaigns || []).map(function (item) {
      return optionHtml(item.public_id, item.title + (item.merchant_name ? ' · ' + item.merchant_name : ''), selectedCampaign);
    }).join('');
    participant.innerHTML = optionHtml('', mode === 'creator' ? 'All participation' : 'All Creators', selectedParticipant) + (data.options.participants || []).map(function (item) {
      var label = mode === 'creator' ? item.campaign_title + (item.merchant_name ? ' · ' + item.merchant_name : '') : item.creator_name + ' · ' + item.campaign_title;
      return optionHtml(item.public_id, label, selectedParticipant);
    }).join('');
  }

  function metric(label, value, detail) {
    return '<article class="mg-cca-metric"><span>' + esc(label) + '</span><strong>' + value + '</strong><small>' + esc(detail || '') + '</small></article>';
  }
  function renderMetrics(data) {
    var summary = data.summary || {};
    var earnings = moneyMap(summary.earnings || {});
    var payoutField = mode === 'creator' ? 'paid_minor' : 'scheduled_minor';
    var payoutLabel = mode === 'creator' ? 'Paid' : 'Scheduled';
    var items = [
      metric('Campaigns', number(summary.campaign_count), number(summary.active_campaigns) + ' active or scheduled'),
      metric(mode === 'creator' ? 'Participation' : 'Creators', number(summary.creator_count), number(summary.assigned) + ' deliverables assigned'),
      metric('Unique clicks', number(summary.unique_clicks), number(summary.views) + ' landing views'),
      metric('Conversions', number(summary.conversions), percent(summary.conversion_rate_bps) + ' click conversion'),
      metric('Deliverables complete', number(summary.completed), percent(summary.completion_rate_bps) + ' completion'),
      '<article class="mg-cca-metric is-money"><span>Net earnings</span><div class="mg-cca-money-list">' + earnings + '</div><small>Append-only earning events</small></article>',
      '<article class="mg-cca-metric is-money"><span>' + payoutLabel + ' payouts</span><div class="mg-cca-money-list">' + moneyMap(summary.payouts || {}, payoutField) + '</div><small>' + number(summary.active_disputes) + ' active disputes</small></article>'
    ];
    if (mode === 'merchant') {
      items.push('<article class="mg-cca-metric is-money"><span>Committed budget</span><div class="mg-cca-money-list">' + moneyMap(summary.budgets || {}, 'committed_minor') + '</div><small>Current immutable budget ledger</small></article>');
    }
    app.querySelector('[data-cca-metrics]').innerHTML = items.join('');
  }

  function renderTrend(data) {
    var rows = data.timeseries || [];
    if (!rows.length) {
      app.querySelector('[data-cca-trend]').innerHTML = '<div class="mg-cca-empty"><strong>No accepted activity in this range</strong><p>Traffic and conversion trends will appear after first-party events are recorded.</p></div>';
      return;
    }
    var max = Math.max.apply(Math, rows.map(function (row) { return Number(row.unique_clicks || 0) + Number(row.conversions || 0); }).concat([1]));
    app.querySelector('[data-cca-trend]').innerHTML = '<div class="mg-cca-trend-legend"><span>Unique clicks</span><span>Conversions</span></div><div class="mg-cca-trend-rows">' + rows.map(function (row) {
      var clicks = Number(row.unique_clicks || 0);
      var conversions = Number(row.conversions || 0);
      return '<div class="mg-cca-trend-row"><time>' + esc(row.bucket) + '</time><div class="mg-cca-bars"><span class="is-click" style="--value:' + Math.max(2, (clicks / max) * 100) + '%" title="' + clicks + ' unique clicks"></span><span class="is-conversion" style="--value:' + Math.max(2, (conversions / max) * 100) + '%" title="' + conversions + ' conversions"></span></div><strong>' + number(clicks) + ' / ' + number(conversions) + '</strong></div>';
    }).join('') + '</div>';
  }

  function mixRow(label, value, total) {
    var width = total > 0 ? Math.max(2, (value / total) * 100) : 0;
    return '<div class="mg-cca-mix-row"><div><span>' + esc(label) + '</span><strong>' + number(value) + '</strong></div><i><b style="--value:' + width + '%"></b></i></div>';
  }
  function renderOverview(data) {
    var summary = data.summary || {};
    var total = Number(summary.conversions || 0);
    app.querySelector('[data-cca-range-title]').textContent = rangeLabel(data.range);
    app.querySelector('[data-cca-conversion-mix]').innerHTML = [
      mixRow('Leads', Number(summary.leads || 0), total),
      mixRow('Checkouts', Number(summary.checkouts || 0), total),
      mixRow('Purchases', Number(summary.purchases || 0), total),
      mixRow('Claims', Number(summary.claims || 0), total),
      mixRow('Redemptions', Number(summary.redemptions || 0), total)
    ].join('');
    var financial = '<div class="mg-cca-financial-group"><span>Net earnings</span><div>' + moneyMap(summary.earnings || {}) + '</div></div>';
    financial += '<div class="mg-cca-financial-group"><span>Scheduled payouts</span><div>' + moneyMap(summary.payouts || {}, 'scheduled_minor') + '</div></div>';
    financial += '<div class="mg-cca-financial-group"><span>Paid payouts</span><div>' + moneyMap(summary.payouts || {}, 'paid_minor') + '</div></div>';
    if (mode === 'merchant') financial += '<div class="mg-cca-financial-group"><span>Budget committed</span><div>' + moneyMap(summary.budgets || {}, 'committed_minor') + '</div></div>';
    app.querySelector('[data-cca-money-summary]').innerHTML = financial;
    renderTrend(data);
  }

  function table(headers, rows, emptyTitle) {
    if (!rows.length) return '<div class="mg-cca-empty"><strong>' + esc(emptyTitle) + '</strong><p>Adjust the filters or wait for campaign activity.</p></div>';
    return '<table class="mg-cca-table"><thead><tr>' + headers.map(function (header) { return '<th>' + esc(header) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows.join('') + '</tbody></table>';
  }
  function renderCampaigns(data) {
    var rows = (data.campaigns || []).map(function (item) {
      return '<tr><td><strong>' + esc(item.title) + '</strong><small>' + esc(item.status) + '</small></td><td>' + number(item.unique_clicks) + '<small>' + number(item.views) + ' views</small></td><td>' + number(item.conversions) + '<small>' + percent(item.conversion_rate_bps) + '</small></td><td>' + number(item.completed) + ' / ' + number(item.assigned) + '<small>' + percent(item.completion_rate_bps) + '</small></td><td><div class="mg-cca-cell-money">' + moneyMap(item.earnings || {}) + '</div></td><td><div class="mg-cca-cell-money">' + moneyMap(item.payouts || {}, 'paid_minor') + '</div></td><td>' + number(item.active_disputes) + '</td></tr>';
    });
    app.querySelector('[data-cca-campaign-table]').innerHTML = table(['Campaign','Traffic','Conversions','Deliverables','Earnings','Paid','Disputes'], rows, 'No campaigns match this range');
  }
  function renderCreators(data) {
    var rows = (data.creators || []).map(function (item) {
      return '<tr><td><strong>' + esc(item.creator_name || 'Creator') + '</strong><small>' + esc(item.campaign_title) + '</small></td><td>' + esc(item.status) + '</td><td>' + number(item.unique_clicks) + '<small>' + number(item.views) + ' views</small></td><td>' + number(item.conversions) + '<small>' + percent(item.conversion_rate_bps) + '</small></td><td>' + number(item.completed) + ' / ' + number(item.assigned) + '<small>' + number(item.revision_rounds) + ' revisions</small></td><td><div class="mg-cca-cell-money">' + moneyMap(item.earnings || {}) + '</div></td><td><div class="mg-cca-cell-money">' + moneyMap(item.payouts || {}, 'paid_minor') + '</div></td></tr>';
    });
    app.querySelector('[data-cca-creator-table]').innerHTML = table([mode === 'creator' ? 'Campaign' : 'Creator','Status','Traffic','Conversions','Deliverables','Earnings','Paid'], rows, 'No Creator performance matches these filters');
  }
  function renderChannels(data) {
    var rows = (data.channels || []).map(function (item) {
      return '<tr><td><strong>' + esc(item.channel) + '</strong><small>' + esc(item.platform) + '</small></td><td>' + number(item.source_count) + '</td><td>' + number(item.unique_clicks) + '<small>' + number(item.views) + ' views</small></td><td>' + number(item.engagements) + '</td><td>' + number(item.conversions) + '</td><td>' + percent(item.conversion_rate_bps) + '</td></tr>';
    });
    app.querySelector('[data-cca-channel-table]').innerHTML = table(['Channel','Sources','Traffic','Engagements','Conversions','Rate'], rows, 'No channel activity in this range');
  }
  function renderFunnel(data) {
    var groups = data.deliverables || {};
    function group(title, items) {
      var total = (items || []).reduce(function (sum, item) { return sum + Number(item.total || 0); }, 0);
      return '<article class="mg-cca-card"><header><h3>' + esc(title) + '</h3><span>' + number(total) + ' total</span></header><div class="mg-cca-funnel-list">' + ((items || []).length ? items.map(function (item) { return '<div><span>' + esc(String(item.status || '').replace(/_/g, ' ')) + '</span><strong>' + number(item.total) + '</strong></div>'; }).join('') : '<p class="mg-cca-muted">No records</p>') + '</div></article>';
    }
    app.querySelector('[data-cca-deliverable-funnel]').innerHTML = group('Assignments', groups.assignments) + group('Submissions', groups.submissions);
  }

  function render(data) {
    state.data = data;
    populateOptions(data);
    renderMetrics(data);
    renderOverview(data);
    renderCampaigns(data);
    renderCreators(data);
    renderChannels(data);
    renderFunnel(data);
    setLoading(false);
    app.querySelector('[data-cca-content]').classList.remove('mg-hidden');
    live('Analytics updated for ' + rangeLabel(data.range) + '.');
  }

  async function load() {
    if (state.loading) return;
    setLoading(true);
    live('Loading Creator Campaign analytics…');
    try {
      var response = await Microgifter.get(endpoint + '?' + queryString());
      render(response.data || response);
    } catch (error) {
      showError(error.message || 'Unable to load Creator Campaign analytics.');
      live('');
    }
  }

  function toggleCustomDates() {
    var custom = app.querySelector('[data-cca-range]').value === 'custom';
    app.querySelectorAll('[data-cca-custom-date]').forEach(function (node) { node.hidden = !custom; });
  }
  function selectTab(name) {
    state.activeTab = name;
    app.querySelectorAll('[data-cca-tab]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.ccaTab === name); });
    app.querySelectorAll('[data-cca-panel]').forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.ccaPanel === name); });
  }

  form.addEventListener('submit', function (event) { event.preventDefault(); load(); });
  app.querySelector('[data-cca-range]').addEventListener('change', toggleCustomDates);
  app.querySelector('[data-cca-reset]').addEventListener('click', function () { form.reset(); toggleCustomDates(); load(); });
  app.querySelector('[data-cca-retry]').addEventListener('click', load);
  app.querySelectorAll('[data-cca-tab]').forEach(function (button) { button.addEventListener('click', function () { selectTab(button.dataset.ccaTab); }); });
  app.querySelector('[data-cca-export]').addEventListener('click', function () {
    var report = app.querySelector('[data-cca-export-report]').value;
    window.location.assign(endpoint + '?' + queryString({ format: 'csv', report: report }));
  });
  app.querySelector('[data-cca-campaign]').addEventListener('change', function () {
    var campaignId = this.value;
    var participant = app.querySelector('[data-cca-participant]');
    Array.from(participant.options).forEach(function (option) {
      if (!option.value || !state.data) { option.hidden = false; return; }
      var row = (state.data.options.participants || []).find(function (item) { return item.public_id === option.value; });
      option.hidden = Boolean(campaignId && row && row.campaign_public_id !== campaignId);
    });
    if (participant.selectedOptions[0] && participant.selectedOptions[0].hidden) participant.value = '';
  });

  toggleCustomDates();
  load();
});
