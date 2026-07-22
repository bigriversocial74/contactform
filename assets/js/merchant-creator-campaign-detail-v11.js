document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var root = document.querySelector('[data-cc-detail]');
  if (!root) return;

  var params = new URLSearchParams(window.location.search);
  var campaignId = String(params.get('campaign') || params.get('campaign_id') || '').trim();
  var loading = root.querySelector('[data-cc-detail-loading]');
  var error = root.querySelector('[data-cc-detail-error]');
  var content = root.querySelector('[data-cc-detail-content]');
  var live = root.querySelector('[data-cc-detail-live]');

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
    });
  }
  function number(value) { return Number(value || 0).toLocaleString(); }
  function percent(numerator, denominator) {
    if (!Number(denominator || 0)) return '0.0%';
    return ((Number(numerator || 0) / Number(denominator)) * 100).toFixed(1) + '%';
  }
  function moneyMap(map, preferredField) {
    var currencies = Object.keys(map || {}).sort();
    if (!currencies.length) return '—';
    return currencies.map(function (currency) {
      var value = map[currency];
      if (value && typeof value === 'object') value = value[preferredField] || value.net_minor || value.amount_minor || 0;
      try { return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency }).format(Number(value || 0) / 100); }
      catch (exception) { return currency + ' ' + (Number(value || 0) / 100).toFixed(2); }
    }).join(' · ');
  }
  function dateLabel(value) {
    if (!value) return '';
    var parsed = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }
  function api(url) {
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) { return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok || payload.ok === false) throw new Error(payload.message || 'Request failed.');
        return payload.data || payload;
      }); });
  }
  function statusClass(status) {
    if (['active', 'completed'].includes(status)) return 'is-green';
    if (['cancelled', 'archived'].includes(status)) return 'is-red';
    if (['draft', 'scheduled'].includes(status)) return 'is-blue';
    return 'is-amber';
  }
  function metric(label, value, detail) {
    return '<article><span>' + esc(label) + '</span><strong>' + value + '</strong><small>' + esc(detail || '') + '</small></article>';
  }
  function funnelRow(label, value, total) {
    var width = total > 0 ? Math.max(3, Math.min(100, (Number(value || 0) / total) * 100)) : 0;
    return '<div><span>' + esc(label) + '</span><i><b style="width:' + width + '%"></b></i><strong>' + number(value) + '</strong><small>' + percent(value, total) + '</small></div>';
  }
  function statusRow(label, value, total, className) {
    return '<div class="' + esc(className || '') + '"><i></i><span>' + esc(label) + '</span><strong>' + number(value) + '</strong><small>' + percent(value, total) + '</small></div>';
  }
  function alertRow(label, value, href) {
    return '<a href="' + esc(href) + '"><span>' + esc(label) + '</span><strong>' + number(value) + '</strong><em>View</em></a>';
  }

  function render(campaign, analytics) {
    var summary = analytics.summary || {};
    var title = campaign.title || 'Creator Campaign';
    var status = String(campaign.status || 'draft');
    root.querySelector('[data-cc-detail-title]').textContent = title;
    var statusElement = root.querySelector('[data-cc-detail-status]');
    statusElement.textContent = status.replace(/_/g, ' ');
    statusElement.className = 'mg-cc-pill ' + statusClass(status);
    root.querySelector('[data-cc-detail-objective]').textContent = campaign.objective || campaign.description || 'Brand–Creator campaign';

    var dates = [dateLabel(campaign.starts_at), dateLabel(campaign.ends_at)].filter(Boolean).join(' – ');
    root.querySelector('[data-cc-detail-meta]').innerHTML = [
      campaign.category ? '<span>' + esc(campaign.category) + '</span>' : '',
      dates ? '<span>' + esc(dates) + '</span>' : '',
      campaign.geographic_label ? '<span>' + esc(campaign.geographic_label) + '</span>' : '',
      campaign.internal_reference ? '<span>' + esc(campaign.internal_reference) + '</span>' : ''
    ].filter(Boolean).join('');

    var encoded = encodeURIComponent(campaign.public_id || campaignId);
    root.querySelector('[data-cc-detail-edit]').href = '/merchant-creator-campaign-builder.php?campaign=' + encoded;
    root.querySelector('[data-cc-detail-invite]').href = '/merchant-creator-participation.php?campaign=' + encoded;
    root.querySelector('[data-cc-detail-preview]').href = '/merchant-creator-analytics.php?campaign_id=' + encoded;

    var metrics = [
      metric('Campaign views', number(summary.views), 'Accepted landing views'),
      metric('Creator clicks', number(summary.unique_clicks), percent(summary.unique_clicks, summary.views) + ' view rate'),
      metric('Signups', number(summary.leads), 'Accepted lead conversions'),
      metric('Product sales', number(summary.purchases), 'Attributed purchases'),
      metric('Conversions', number(summary.conversions), percent(summary.conversions, summary.unique_clicks) + ' click rate'),
      metric('Creator earnings', esc(moneyMap(summary.earnings || {}, 'net_minor')), 'Append-only events'),
      metric('Budget committed', esc(moneyMap(summary.budgets || {}, 'committed_minor')), 'Merchant-only ledger')
    ];
    root.querySelector('[data-cc-detail-metrics]').innerHTML = metrics.join('');

    var funnelTotal = Math.max(Number(summary.views || 0), 1);
    root.querySelector('[data-cc-detail-funnel]').innerHTML = [
      funnelRow('Campaign views', summary.views, funnelTotal),
      funnelRow('Creator clicks', summary.unique_clicks, funnelTotal),
      funnelRow('Checkouts', summary.checkouts, funnelTotal),
      funnelRow('Purchases', summary.purchases, funnelTotal),
      funnelRow('Claims', summary.claims, funnelTotal),
      funnelRow('Redemptions', summary.redemptions, funnelTotal)
    ].join('');

    var creatorTotal = Math.max(Number(summary.creator_count || 0), 1);
    root.querySelector('[data-cc-detail-creators]').innerHTML = [
      statusRow('Creators', summary.creator_count, creatorTotal, 'is-blue'),
      statusRow('Active or scheduled campaigns', summary.active_campaigns, Math.max(Number(summary.campaign_count || 0), 1), 'is-green'),
      statusRow('Active disputes', summary.active_disputes, creatorTotal, 'is-red')
    ].join('');

    var assigned = Number(summary.assigned || 0);
    var completed = Number(summary.completed || 0);
    var deliverableTotal = Math.max(assigned, 1);
    root.querySelector('[data-cc-detail-deliverables]').innerHTML = [
      statusRow('Assigned', assigned, deliverableTotal, 'is-blue'),
      statusRow('Completed', completed, deliverableTotal, 'is-green'),
      statusRow('Open', Math.max(0, assigned - completed), deliverableTotal, 'is-amber'),
      statusRow('Revision rounds', summary.revision_rounds || 0, deliverableTotal, 'is-red')
    ].join('');

    root.querySelector('[data-cc-detail-alerts]').innerHTML = [
      alertRow('Applications and agreements', Math.max(0, Number(summary.creator_count || 0) - Number(summary.completed || 0)), '/merchant-creator-participation.php?campaign=' + encoded),
      alertRow('Deliverables still open', Math.max(0, assigned - completed), '/merchant-creator-deliverables.php?campaign=' + encoded),
      alertRow('Active disputes', summary.active_disputes || 0, '/merchant-creator-payouts.php?campaign=' + encoded)
    ].join('');

    var timeseries = (analytics.timeseries || []).slice(-5).reverse();
    root.querySelector('[data-cc-detail-activity]').innerHTML = timeseries.length ? timeseries.map(function (item) {
      return '<div><i></i><span><strong>' + esc(item.bucket || 'Activity') + '</strong><small>' + number(item.unique_clicks) + ' clicks · ' + number(item.conversions) + ' conversions</small></span></div>';
    }).join('') : '<p class="mg-v11-empty-copy">No accepted attributed activity is available for this campaign yet.</p>';

    var creators = (analytics.creators || []).slice(0, 6);
    root.querySelector('[data-cc-detail-top-creators]').innerHTML = creators.length ? creators.map(function (item, index) {
      return '<div><b>' + (index + 1) + '</b><span><strong>' + esc(item.creator_name || 'Creator') + '</strong><small>' + number(item.conversions) + ' conversions · ' + number(item.unique_clicks) + ' clicks</small></span><em>' + esc(moneyMap(item.earnings || {}, 'net_minor')) + '</em></div>';
    }).join('') : '<p class="mg-v11-empty-copy">Creator performance will appear after participation and accepted activity are recorded.</p>';

    loading.classList.add('mg-hidden');
    error.classList.add('mg-hidden');
    content.classList.remove('mg-hidden');
    live.textContent = title + ' campaign detail loaded.';
  }

  function fail(message) {
    loading.classList.add('mg-hidden');
    content.classList.add('mg-hidden');
    root.querySelector('[data-cc-detail-error-message]').textContent = message || 'Unable to load campaign detail.';
    error.classList.remove('mg-hidden');
    live.textContent = '';
  }

  function load() {
    if (!campaignId) {
      fail('A campaign identifier is required. Open a campaign from the Creator Campaign overview.');
      return;
    }
    loading.classList.remove('mg-hidden');
    error.classList.add('mg-hidden');
    content.classList.add('mg-hidden');
    var encoded = encodeURIComponent(campaignId);
    Promise.all([
      api('/api/merchant/creator-campaigns.php?action=detail&campaign_id=' + encoded),
      api('/api/merchant/creator-campaign-analytics.php?campaign_id=' + encoded + '&range=last_30_days')
    ]).then(function (responses) {
      render(responses[0].campaign || {}, responses[1] || {});
    }).catch(function (exception) { fail(exception.message); });
  }

  root.querySelector('[data-cc-detail-retry]')?.addEventListener('click', load);
  load();
});